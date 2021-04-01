<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

class ActivitiesImportResult
{
    private const GUID_FOR_NO_ACTIVITY = '';
    /**
     * @var int
     */
    private $importedCount = 0;
    /**
     * @var string|null
     */
    private $processedFilename;

    /**
     * @var string[][]
     */
    private $successMessages = [];
    /**
     * @var string[][]
     */
    private $warningMessages = [];
    /**
     * @var string[][]
     */
    private $errorLikeWarningMessages = [];
    /**
     * @var string[][]
     */
    private $errorMessages = [];

    public function incrementImportedCount(): int {
        $this->importedCount++;
        return $this->importedCount;
    }

    public function setProcessedFilename(string $processedFilename): ActivitiesImportResult {
        if ($this->processedFilename !== null && $this->processedFilename !== $processedFilename) {
            throw new \LogicException(sprintf('Processed filename is already set to %s and can not be changed to %s.', $this->processedFilename, $processedFilename));
        }
        if ($processedFilename === '') {
            throw new \LogicException('Processed filename has empty name.');
        }
        $this->processedFilename = $processedFilename;
        return $this;
    }

    public function addErrorMessage(string $errorMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->errorMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $errorMessage;
        return $this;
    }

    public function addWarningMessage(string $warningMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->warningMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $warningMessage;
        return $this;
    }

    public function addWarnings(ImportStepResult $importStepResult, ?string $activityGuid): ActivitiesImportResult {
        foreach ($importStepResult->getWarnings() as $warningMessage) {
            $this->addWarningMessage($warningMessage, $activityGuid);
        }
        return $this;
    }

    public function addErrorLikeWarnings(ImportStepResult $importStepResult, ?string $activityGuid): ActivitiesImportResult {
        foreach ($importStepResult->getErrorLikeWarnings() as $errorLikeWarningMessage) {
            $this->addErrorLikeWarningMessage($errorLikeWarningMessage, $activityGuid);
        }
        return $this;
    }

    public function addErrorLikeWarningMessage(string $errorLikeWarningMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->errorLikeWarningMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $errorLikeWarningMessage;
        return $this;
    }

    public function addSuccessMessage(string $successMessage, ?string $activityGuid): ActivitiesImportResult {
        $this->successMessages[$activityGuid ?? self::GUID_FOR_NO_ACTIVITY][] = $successMessage;
        return $this;
    }

    public function solveActivityDescription(string $activityGuidToSolve, string $activityFinalDescription) {
        $this->errorMessages = $this->addActivityDescription($this->errorMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->errorLikeWarningMessages = $this->addActivityDescription($this->errorLikeWarningMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->warningMessages = $this->addActivityDescription($this->warningMessages, $activityGuidToSolve, $activityFinalDescription);
        $this->successMessages = $this->addActivityDescription($this->successMessages, $activityGuidToSolve, $activityFinalDescription);
    }

    private function addActivityDescription(array $messagesByGuid, string $activityGuidToSolve, string $activityFinalDescription): array {
        if (!isset($messagesByGuid[$activityGuidToSolve])) {
            return $messagesByGuid;
        }
        foreach ($messagesByGuid[$activityGuidToSolve] as &$message) {
            $message = "$activityFinalDescription $message";
        }
        return $messagesByGuid;
    }

    public function getImportedCount(): int {
        return $this->importedCount;
    }

    public function getProcessedFilename(): ?string {
        return $this->processedFilename;
    }

    /**
     * @return string[]
     */
    public function getSuccessMessages(): array {
        return $this->getFlattenedByOneLevel($this->successMessages);
    }

    /**
     * @return string[]
     */
    public function getWarningMessages(): array {
        return $this->getFlattenedByOneLevel($this->warningMessages);
    }

    /**
     * Without messages about errored activities
     * @return string[]
     */
    public function getErrorLikeAndWarningMessagesExceptErrored(): array {
        $errorLikeAndWarnings = array_merge_recursive(
            $this->errorLikeWarningMessages,
            $this->warningMessages
        );
        $exceptGuidsAsKeys = $this->errorMessages;
        unset($exceptGuidsAsKeys[self::GUID_FOR_NO_ACTIVITY]);
        $filtered = array_diff_key(
            $errorLikeAndWarnings,
            $exceptGuidsAsKeys
        );
        return $this->getFlattenedByOneLevel($filtered);
    }

    /**
     * @return string[]
     */
    public function getErrorMessages(): array {
        return $this->getFlattenedByOneLevel($this->errorMessages);
    }

    /**
     * @param string[][] $array
     * @return string[]
     */
    private function getFlattenedByOneLevel(array $array): array {
        $flattened = [];
        foreach ($array as $subArray) {
            foreach ($subArray as $value) {
                $flattened[] = $value;
            }
        }
        return $flattened;
    }
}
