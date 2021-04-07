<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ActivitiesImportException;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;

class ActivitiesImporter
{

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var int
     */
    private $userId;
    /**
     * @var Logovac
     */
    private $logovac;
    /**
     * @var string
     */
    private $mutexKey;
    /**
     * @var Mutex
     */
    private $mutexPattern;
    /**
     * @var Mutex
     */
    private $mutexForProgramLine;
    /**
     * @var ImportValuesDescriber
     */
    private $importValuesDescriber;
    /**
     * @var ImportValuesReader
     */
    private $importValuesReader;
    /**
     * @var ImagesImporter
     */
    private $imagesImporter;
    /**
     * @var ImportValuesSanitizer
     */
    private $importValuesSanitizer;
    /**
     * @var ImportRequirementsGuardian
     */
    private $importRequirementsGuardian;
    /**
     * @var ActivityImporter
     */
    private $activityImporter;
    /**
     * @var string
     */
    private $errorsListUrl;
    /**
     * @var ActivitiesImportLogger
     */
    private $activitiesImportLogger;

    public function __construct(
        int $userId,
        GoogleDriveService $googleDriveService,
        GoogleSheetsService $googleSheetsService,
        int $currentYear,
        string $editActivityUrlSkeleton,
        \DateTimeInterface $now,
        string $storytellersPermissionsUrl,
        Logovac $logovac,
        string $baseUrl,
        Mutex $mutexPattern,
        string $errorsListUrl,
        ActivitiesImportLogger $activitiesImportLogger
    ) {
        $this->userId = $userId;
        $this->googleDriveService = $googleDriveService;
        $this->logovac = $logovac;
        $this->mutexPattern = $mutexPattern;

        $importValuesDescriber = new ImportValuesDescriber($editActivityUrlSkeleton);
        $importObjectsContainer = new ImportObjectsContainer(new ImportUsersCache());
        $importAccessibilityChecker = new ImportSqlMappedValuesChecker($currentYear, $now, $importValuesDescriber);

        $this->importValuesDescriber = $importValuesDescriber;
        $this->importValuesReader = new ImportValuesReader($googleSheetsService, $logovac);
        $this->imagesImporter = new ImagesImporter($baseUrl, $importValuesDescriber);
        $this->importValuesSanitizer = new ImportValuesSanitizer($importValuesDescriber, $importObjectsContainer, $currentYear, $storytellersPermissionsUrl);
        $this->importRequirementsGuardian = new ImportRequirementsGuardian($importObjectsContainer);
        $this->activityImporter = new ActivityImporter($importValuesDescriber, $importAccessibilityChecker, $now, $currentYear, $logovac);
        $this->errorsListUrl = $errorsListUrl;
        $this->activitiesImportLogger = $activitiesImportLogger;
    }

    public function importActivities(string $spreadsheetId): ActivitiesImportResult {
        $result = new ActivitiesImportResult();
        try {
            $processedFileNameResult = $this->getProcessedFileName($spreadsheetId);
            if ($processedFileNameResult->isError()) {
                $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $processedFileNameResult->getError()), null);
                return $result;
            }
            $processedFileName = $processedFileNameResult->getSuccess();
            unset($processedFileNameResult);
            $result->setProcessedFilename($processedFileName);

            $activitiesValuesResult = $this->importValuesReader->getIndexedValues($spreadsheetId);
            if ($activitiesValuesResult->isError()) {
                $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $activitiesValuesResult->getError()), null);
                return $result;
            }
            $activitiesValues = $activitiesValuesResult->getSuccess();
            unset($activitiesValuesResult);

            $singleProgramLineResult = $this->importRequirementsGuardian->guardSingleProgramLineOnly($activitiesValues, $processedFileName);
            if ($singleProgramLineResult->isError()) {
                $result->addErrorMessage(sprintf('%s Import byl <strong>přerušen</strong>.', $singleProgramLineResult->getError()), null);
                return $result;
            }
            /** @var \Typ $singleProgramLine */
            $singleProgramLine = $singleProgramLineResult->getSuccess();
            unset($singleProgramLineResult);

            if (!$this->getExclusiveLock($singleProgramLine->nazev())) {
                $result->addErrorLikeWarningMessage(
                    sprintf(
                        "Právě probíhá jiný import aktivit z programové linie '%s'. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.",
                        mb_ucfirst($singleProgramLine->nazev())
                    ),
                    null
                );
                return $result;
            }

            $potentialImageUrlsPerActivity = [];
            foreach ($activitiesValues as $activityValues) {
                $activityGuid = uniqid('importActivity', true);

                $validatedValuesResult = $this->importValuesSanitizer->sanitizeValues($singleProgramLine, $activityValues);
                if ($validatedValuesResult->isError()) {
                    $result->addErrorMessage($validatedValuesResult->getError(), $activityGuid);
                    $activityFinalDescription = $validatedValuesResult->getLastActivityDescription()
                        ?? $this->importValuesDescriber->describeActivityByInputValues($activityValues, null);
                    $result->solveActivityDescription($activityGuid, $activityFinalDescription);
                    continue;
                }
                if ($validatedValuesResult->hasWarnings()) {
                    $result->addWarnings($validatedValuesResult, $activityGuid);
                }
                if ($validatedValuesResult->hasErrorLikeWarnings()) {
                    $result->addErrorLikeWarnings($validatedValuesResult, $activityGuid);
                }
                $validatedValues = $validatedValuesResult->getSuccess();
                unset($validatedValuesResult);
                [
                    'values' => $sqlMappedValues,
                    'originalActivity' => $originalActivity,
                    'longAnnotation' => $longAnnotation,
                    'storytellersIds' => $storytellersIds,
                    'tagIds' => $tagIds,
                    'potentialImageUrls' => $potentialImageUrls,
                ] = $validatedValues;

                $importActivityResult = $this->activityImporter->importActivity(
                    $sqlMappedValues,
                    $longAnnotation,
                    $storytellersIds,
                    $tagIds,
                    $singleProgramLine,
                    $potentialImageUrls,
                    $originalActivity
                );
                $result->addWarnings($importActivityResult, $activityGuid);
                $result->addErrorLikeWarnings($importActivityResult, $activityGuid);
                if ($importActivityResult->isError()) {
                    $result->addErrorMessage($importActivityResult->getError(), $activityGuid);
                    $activityFinalDescription = $this->importValuesDescriber->describeActivityBySqlMappedValues($sqlMappedValues, $originalActivity);
                    $result->solveActivityDescription($activityGuid, $activityFinalDescription);
                    continue;
                }
                /** @var \Aktivita $importedActivity */
                ['message' => $successMessage, 'importedActivity' => $importedActivity] = $importActivityResult->getSuccess();
                if ($importActivityResult->hasErrorLikeWarnings()) {
                    $result->addWarningMessage($successMessage . ' Import ale nebyl bez problémů, viz výše.', $activityGuid);
                } else {
                    $result->addSuccessMessage($successMessage, $activityGuid);
                }
                unset($importActivityResult);

                if (count($potentialImageUrls) > 0) {
                    $potentialImageUrlsPerActivity[$importedActivity->id()] = $potentialImageUrls;
                }

                $result->incrementImportedCount();

                $activityFinalDescription = $this->importValuesDescriber->describeActivity($importedActivity);
                $result->solveActivityDescription($activityGuid, $activityFinalDescription);
            }
        } catch (\Exception $exception) {
            $result->addErrorMessage(<<<HTML
Něco se <a href="{$this->errorsListUrl}" target="_blank">nepovedlo</a>. Import byl <strong>přerušen</strong>. Zkus to za chvíli znovu.
HTML
                , null
            );
            $this->logovac->zaloguj($exception);
            $this->releaseExclusiveLock();
            return $result;
        }
        $savingImagesResult = $this->imagesImporter->saveImages($potentialImageUrlsPerActivity);
        if ($savingImagesResult->hasWarnings()) {
            $result->addWarnings($savingImagesResult, null);
        }
        if ($savingImagesResult->hasErrorLikeWarnings()) {
            $result->addErrorLikeWarnings($savingImagesResult, null);
        }
        if ($result->getImportedCount() > 0) {
            $this->activitiesImportLogger->logUsedSpreadsheet($this->userId, $spreadsheetId, new \DateTimeImmutable());
        }
        $this->releaseExclusiveLock();
        return $result;
    }

    private function getProcessedFileName(string $spreadsheetId): ImportStepResult {
        try {
            $filename = $this->googleDriveService->getFileName($spreadsheetId);
        } catch (GoogleConnectionException | \Google_Service_Exception $connectionException) {
            $this->logovac->zaloguj($connectionException);
            return ImportStepResult::error('Google Sheets API je dočasně nedostupné. Zkus to za chvíli znovu.');
        }
        if ($filename === null) {
            return ImportStepResult::error(sprintf("Žádný soubor nebyl na Google API nalezen pod ID '$spreadsheetId'."));
        }
        return ImportStepResult::success($filename);
    }

    private function getExclusiveLock(string $identifier): bool {
        $mutex = $this->createMutexForProgramLine($identifier);
        return $mutex->cekejAZamkni(3500 /* milliseconds */, new \DateTimeImmutable('+1 minute'), $this->createMutexKey($identifier), $this->userId);
    }

    private function createMutexForProgramLine(string $identifier): Mutex {
        if (!$this->mutexForProgramLine) {
            $this->mutexForProgramLine = $this->mutexPattern->dejProPodAkci($identifier);
        }
        return $this->mutexForProgramLine;
    }

    private function releaseExclusiveLock() {
        if (!$this->hasMutexForProgramLine()) {
            return;
        }
        $this->getMutexForProgramLine()->odemkni($this->getMutexKey());
    }

    private function hasMutexForProgramLine() {
        return $this->mutexForProgramLine && $this->mutexForProgramLine->zamceno();
    }

    private function getMutexForProgramLine(): Mutex {
        if (!$this->mutexForProgramLine) {
            throw new ActivitiesImportException('Mutex for imported program line does not exists yet');
        }
        return $this->mutexForProgramLine;
    }

    private function createMutexKey(string $programLine): string {
        if ($this->mutexKey === null) {
            $this->mutexKey = uniqid($programLine . '-', true);
        }
        return $this->mutexKey;
    }

    private function getMutexKey(): string {
        if (!$this->mutexKey) {
            throw new ActivitiesImportException('Mutex key is empty');
        }
        return $this->mutexKey;
    }

    private function getResultTypeName(ImportStepResult $resultOfImportStep): string {
        $nameParts = [];
        if ($resultOfImportStep->isError()) {
            $nameParts[] = 'error';
        }
        if ($resultOfImportStep->isSuccess()) {
            $nameParts[] = 'success';
        }
        if ($resultOfImportStep->hasWarnings()) {
            $nameParts[] = 'warnings';
        }
        return implode(',', $nameParts);
    }

}
