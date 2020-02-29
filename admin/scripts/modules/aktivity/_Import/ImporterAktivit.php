<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\DuplicatedUnifiedKeyException;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;

class ImporterAktivit
{

  /**
   * @var GoogleDriveService
   */
  private $googleDriveService;
  /**
   * @var GoogleSheetsService
   */
  private $googleSheetsService;
  /**
   * @var int
   */
  private $userId;
  /**
   * @var int
   */
  private $currentYear;
  /**
   * @var \DateTimeInterface
   */
  private $now;
  /**
   * @var Logovac
   */
  private $logovac;
  /**
   * @var array|\Aktivita[]|null[]
   */
  private $oldActivities = [];
  /**
   * @var array|\Typ[][]
   */
  private $programLinesCache;
  /**
   * @var array|\Typ[][]
   */
  private $programLocationsCache;
  /**
   * @var array|\Tag[][]
   */
  private $tagsCache;
  /**
   * @var array|\Stav[][]
   */
  private $StatesCache;
  /**
   * @var \Uzivatel[]
   */
  private $storytellersCache;
  /**
   * @var array|int[]
   */
  private $keyUnifyDepth = [];
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
   * @var string
   */
  private $editActivityUrlSkeleton;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    int $currentYear,
    \DateTimeInterface $now,
    string $editActivityUrlSkeleton,
    Logovac $logovac,
    Mutex $mutexPattern
  ) {
    $this->userId = $userId;
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->currentYear = $currentYear;
    $this->now = $now;
    $this->logovac = $logovac;
    $this->mutexPattern = $mutexPattern;
    $this->editActivityUrlSkeleton = $editActivityUrlSkeleton;
  }

  public function importujAktivity(string $spreadsheetId): array {
    $result = [
      'importedCount' => 0,
      'processedFileName' => null,
      'messages' => [
        'successes' => [],
        'warnings' => [],
        'errors' => [],
      ],
    ];
    try {
      ['success' => $processedFileName, 'error' => $processedFileNameError] = $this->getProcessedFileName($spreadsheetId);
      if ($processedFileNameError) {
        $result['messages']['errors'][] = $processedFileNameError;
        return $result;
      }
      $result['processedFileName'] = $processedFileName;

      ['success' => $activitiesValues, 'error' => $activitiesValuesError] = $this->getIndexedValues($spreadsheetId);
      if ($activitiesValuesError) {
        $result['messages']['errors'][] = $activitiesValuesError;
        return $result;
      }

      ['success' => $programLine, 'error' => $singleProgramLineError] = $this->guardSingleProgramLineOnly($activitiesValues);
      if ($singleProgramLineError) {
        return $this->error($singleProgramLineError);
      }

      if (!$this->getExclusiveLock($programLine)) {
        $result['messages']['warnings'][] = sprintf("Právě probíhá jiný import aktivit z programové linie '%s'. Zkus to za chvíli znovu.", mb_ucfirst($programLine));
        return $result;
      }

      /** @var int|null $parentActivityId */
      $parentActivityId = null;
      foreach ($activitiesValues as $activityValues) {
        ['success' => $activityId, 'error' => $activityIdError] = $this->getActivityId($activityValues);
        if ($activityIdError) {
          $result['messages']['errors'][] = $activityIdError;
          continue;
        }

        $aktivita = null;
        if ($activityId) {
          ['success' => $aktivita, 'error' => $aktivitaError] = $this->getValidatedOldActivityById($activityId);
          if ($aktivitaError) {
            $result['messages']['errors'][] = $aktivitaError;
            continue;
          }
        }

        ['success' => $validatedValues, 'error' => $validatedValuesError] = $this->validateValues($activityValues, $aktivita);
        if ($validatedValuesError) {
          return $this->error($validatedValuesError);
        }

        ['success' => $importActivitySuccess, 'warning' => $importActivityWarning, 'error' => $importActivityError] = $this->importActivity($validatedValues, $aktivita);
        if ($importActivityWarning) {
          $result['messages']['warnings'][] = $importActivityWarning;
        }
        if ($importActivityError) {
          $result['messages']['errors'][] = $importActivityError;
          continue;
        }
        if ($importActivitySuccess) {
          $result['messages']['successes'][] = $importActivitySuccess;
          $result['importedCount']++;
        }
      }
    } catch (\Google_Service_Exception $exception) {
      $result['messages']['errors'][] = 'Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.';
      $this->logovac->zaloguj($exception);
      $this->releaseExclusiveLock();
      return $result;
    }
    $this->releaseExclusiveLock();
    return $result;
  }

  private function getProcessedFileName(string $spreadsheetId): array {
    $filename = $this->googleDriveService->getFileName($spreadsheetId);
    if ($filename === null) {
      return $this->error(sprintf("Žádný soubor nebyl na Google API nalezen pod ID '$spreadsheetId'"));
    }
    return $this->success($filename);
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
    $this->getMutexForProgramLine()->odemkni($this->getMutexKey());
  }

  private function getMutexForProgramLine(): Mutex {
    if (!$this->mutexForProgramLine) {
      throw new ImportAktivitException('Mutex for imported program line does not exists yet');
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
      throw new ImportAktivitException('Mutex key is empty');
    }
    return $this->mutexKey;
  }

  private function importInstance(int $parentActivityId, array $values): array {
    return $this->success("TODO Zatím nic s instancí mateřské aktivity $parentActivityId: " . implode(';', $values));
  }

  private function success($success): array {
    return ['success' => $success, 'warning' => false, 'error' => false];
  }

  private function warning(string $warning): array {
    return ['success' => false, 'warning' => $warning, 'error' => false];
  }

  private function error(string $error): array {
    return ['success' => false, 'warning' => false, 'error' => $error];
  }

  private function result($success, ?string $warning, ?string $error): array {
    return ['success' => $success, 'warning' => $warning, 'error' => $error];
  }

  private function mayBeInstance(array $activityValues, ?int $parentActivityId): bool {
    if (!empty($activityValues[ExportAktivitSloupce::ID_AKTIVITY])) {
      $aktivita = \Aktivita::zId((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
      return (bool)$aktivita->patriPod();
    }
    $programovaLinie = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null; // may be name or ID
    $url = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if ($url && $programovaLinie) {
      $parentId = dbOneCol(<<<SQL
SELECT MIN(id_akce) AS parent_id
FROM akce_seznam
INNER JOIN akce_typy ON akce_typy.id_typu = akce_seznam.typ
WHERE url_akce = $1 AND rok = $2 AND (akce_typy.typ_1pmn = $3 OR akce_typy.id_typu = $3)
SQL
        , [$url, $this->currentYear, $programovaLinie /* name or ID */]
      );
      if ($parentId) {
        return true;
      }
    }
    if ($url) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2
SQL
        , [$url, $this->currentYear]
      );
      if (count($ids) === 1) { // without type there may be more IDs per URL-and-year
        return true;
      }
    }
    $nazevAkce = $activityValues[ExportAktivitSloupce::NAZEV];
    if ($nazevAkce) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2
SQL
        , [$nazevAkce, $this->currentYear]
      );
      if (count($ids) === 1) { // there may be more IDs per name-and-year
        return true;
      }
    }
    return false;
  }

  private function getValidatedOldActivityById(int $id): array {
    $aktivita = $this->findOldActivityById($id);
    if ($aktivita) {
      return $this->success($aktivita);
    }
    return $this->error(sprintf('Aktivita s ID %d neexistuje. Nelze ji proto importem upravit', $id));
  }

  private function findOldActivityById(int $id): ?\Aktivita {
    if (!array_key_exists($id, $this->oldActivities)) {
      $this->oldActivities[$id] = \Aktivita::zId($id);
    }
    return $this->oldActivities[$id];
  }

  private function getActivityId(array $activityValues): array {
    if ($activityValues[ExportAktivitSloupce::ID_AKTIVITY]) {
      return $this->success((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
    }
    return $this->success(null);
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

  private function getIndexedValues(string $spreadsheetId): array {
    try {
      $rawValues = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
    } catch (GoogleApiException $exception) {
      $this->logovac->zaloguj($exception);
      return $this->error('Google Sheets API je dočasně nedostupné, zkuste to znovu za chvíli.');
    }
    ['success' => $cleansedValues, 'error' => $cleansedValuesError] = $this->cleanseValues($rawValues);
    if ($cleansedValuesError) {
      return $this->error($cleansedValuesError);
    }
    ['success' => $cleansedHeader, 'error' => $cleansedHeaderError] = $this->getCleansedHeader($cleansedValues);
    if ($cleansedHeaderError) {
      return $this->error($cleansedHeaderError);
    }
    unset($cleansedValues[array_key_first($cleansedValues)]); // remove row with header

    $indexedValues = [];
    $positionsOfValuesWithoutHeaders = [];
    foreach ($cleansedValues as $cleansedRow) {
      $indexedRow = [];
      foreach ($cleansedRow as $columnIndex => $cleansedValue) {
        $columnName = $cleansedHeader[$columnIndex] ?? false;
        if ($columnName) {
          $indexedRow[$columnName] = $cleansedValue;
        } else if ($cleansedValue !== '') {
          $positionsOfValuesWithoutHeaders[$columnIndex] = $columnIndex + 1;
        }
      }
      if (count($positionsOfValuesWithoutHeaders) > 0) {
        $this->error(sprintf('Některým sloupcům chybí název a to na pozicích %s', implode(',', $positionsOfValuesWithoutHeaders)));
      }
      $indexedValues[] = $indexedRow;
    }
    return $this->success($indexedValues);
  }

  private function getCleansedHeader(array $values): array {
    $unifiedKnownColumns = [];
    foreach (ExportAktivitSloupce::vsechnySloupce() as $knownColumn) {
      $keyFromColumn = self::toUnifiedKey($knownColumn, $unifiedKnownColumns, self::UNIFY_UP_TO_LETTERS);;
      $unifiedKnownColumns[$keyFromColumn] = $knownColumn;
    }
    $header = reset($values);
    $cleansedHeader = [];
    $unknownColumns = [];
    $emptyColumnsPositions = [];
    foreach ($header as $index => $value) {
      $unifiedValue = self::toUnifiedKey($value, [], self::UNIFY_UP_TO_LETTERS);
      if (array_key_exists($unifiedValue, $unifiedKnownColumns)) {
        $cleansedHeader[$index] = $unifiedKnownColumns[$unifiedValue];
      } else if ($value === '') {
        $emptyColumnsPositions[$index] = $index + 1;
      } else {
        $unknownColumns[] = $value;
      }
    }
    if (count($unknownColumns) > 0) {
      return $this->error(sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
        return "'$value'";
      }, $unknownColumns))));
    }
    if (count($cleansedHeader) === 0) {
      return $this->error('Chybí názvy sloupců v prvním řádku');
    }
    if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
      return $this->error(sprintf('Některé náxvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)));
    }
    return $this->success($cleansedHeader);
  }

  private function cleanseValues(array $values): array {
    $cleansedValues = [];
    foreach ($values as $row) {
      $cleansedRow = [];
      $rowIsEmpty = true;
      foreach ($row as $value) {
        $cleansedValue = trim($value);
        $cleansedRow[] = $cleansedValue;
        $rowIsEmpty = $rowIsEmpty && $cleansedValue === '';
      }
      if (!$rowIsEmpty) {
        $cleansedValues[] = $cleansedRow;
      }
    }
    if (count($cleansedValues) === 0) {
      return $this->error('Žádná data. Import je prázdný.');
    }
    return $this->success($cleansedValues);
  }

  private function importActivity(array $validatedValues, ?\Aktivita $existingAcivity): array {
    if ($existingAcivity) {
      if (!$existingAcivity->bezpecneEditovatelna()) {
        return $this->error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože je ve stavu '%s'",
          $this->describeActivity($existingAcivity), $existingAcivity->stav()->nazev()
        ));
      }
      if ($existingAcivity->zacatek() && $existingAcivity->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
        return $this->error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už začala (začátek v %s)",
          $this->describeActivity($existingAcivity), $existingAcivity->zacatek()->formatCasNaMinutyStandard()
        ));
      }
      if ($existingAcivity->konec() && $existingAcivity->konec()->getTimestamp() <= $this->now->getTimestamp()) {
        return $this->error(sprintf(
          "Aktivitu %s už nelze editovat importem, protože už skončila (konec v %s)",
          $this->describeActivity($existingAcivity),
          $existingAcivity->konec()->formatCasNaMinutyStandard()
        ));
      }
    }

    [
      'values' => $values,
      'longAnnotation' => $longAnnotation,
      'storytellersIds' => $storytellersIds,
      'tagIds' => $tagIds,
    ] = $validatedValues;

    ['error' => $storytellersAccessibilityError] = $this->checkStorytellersAccessibility(
      $storytellersIds,
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $existingAcivity
        ? $existingAcivity->id()
        : null,
      $values
    );
    if ($storytellersAccessibilityError) {
      return $this->error($storytellersAccessibilityError);
    }

    ['warning' => $locationAccessibilityWarning, 'error' => $locationAccessibilityError] = $this->checkLocationByAccessibility(
      $values[AktivitaSqlSloupce::LOKACE],
      $values[AktivitaSqlSloupce::ZACATEK],
      $values[AktivitaSqlSloupce::KONEC],
      $existingAcivity
        ? $existingAcivity->id()
        : null,
      $values
    );
    if ($locationAccessibilityError) {
      return $this->error($locationAccessibilityError);
    }

    /** @var \Aktivita $savedActivity */
    ['success' => $savedActivity, 'error' => $savedActivityError] = $this->saveActivity($values, $longAnnotation, $storytellersIds, $tagIds, $existingAcivity);

    if ($savedActivityError) {
      return $this->error($savedActivityError);
    }
    if ($existingAcivity) {
      return $this->result(
        sprintf('Upravena aktivita <a target="_blank" href="%s%d">%s</a>', $this->editActivityUrlSkeleton, $savedActivity->id(), $this->describeActivity($savedActivity)),
        $locationAccessibilityWarning ?: null,
        null
      );
    }
    return $this->result(
      sprintf('Nahrána nová aktivita <a target="_blank" href="%s%d">%s</a>', $this->editActivityUrlSkeleton, $savedActivity->id(), $this->describeActivity($savedActivity)),
      $locationAccessibilityWarning ?: null,
      null
    );
  }

  private function guardSingleProgramLineOnly(array $activitiesValues): array {
    $programLineNames = [];
    foreach ($activitiesValues as $row) {
      $programLineName = null;
      $programLineValue = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLineValue) {
        $programLine = $this->getProgramLineFromValue((string)$programLineValue);
        if ($programLine) {
          $programLineName = $programLine->nazev();
        }
      }
      if (!$programLineName && $row[ExportAktivitSloupce::ID_AKTIVITY]) {
        $aktivita = \Aktivita::zId($row[ExportAktivitSloupce::ID_AKTIVITY]);
        if ($aktivita) {
          $programLineName = $aktivita->typ() && $aktivita->typ()->nazev();
        }
      }
      if ($programLineName && !in_array($programLineName, $programLineNames, true)) {
        $programLineNames[] = $programLineName;
      }
    }
    if (count($programLineNames) > 1) {
      return $this->error(sprintf(
        'Importovat lze pouze jednu programovou linii. Importní soubor jich má %d: %s',
        count($programLineNames),
        implode(',', self::wrapByQuotes($programLineNames))
      ));
    }
    if (count($programLineNames) === 0) {
      return $this->error('V importovaném souboru chybí programová linie, nebo alespoň existující aktivita s nastavenou programovou linií.');
    }
    return $this->success(reset($programLineNames));
  }

  private function checkStorytellersAccessibility(array $storytellersIds, ?string $zacatekString, ?string $konecString, ?int $currentActivityId, array $values): array {
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return $this->success(true);
    }
    /** @var DateTimeCz $zacatek */
    /** @var DateTimeCz $konec */
    ['start' => $zacatek, 'end' => $konec] = $rangeDates;
    $occupiedStorytellers = dbArrayCol(<<<SQL
SELECT akce_organizatori.id_uzivatele, GROUP_CONCAT(akce_organizatori.id_akce SEPARATOR ',') AS activity_ids
FROM akce_organizatori
JOIN akce_seznam ON akce_organizatori.id_akce = akce_seznam.id_akce
WHERE akce_seznam.zacatek >= $1
AND akce_seznam.konec <= $2
AND CASE
    WHEN $3 IS NULL THEN TRUE
    ELSE akce_seznam.id_akce != $3
    END
GROUP BY akce_organizatori.id_uzivatele
SQL
      , [$zacatek->format(DateTimeCz::FORMAT_DB), $konec->format(DateTimeCz::FORMAT_DB), $currentActivityId]
    );
    $conflictingStorytellers = array_intersect_key($occupiedStorytellers, array_fill_keys($storytellersIds, true));
    if (!$conflictingStorytellers) {
      return $this->success(true);
    }
    $errors = [];
    foreach ($conflictingStorytellers as $conflictingStorytellerId => $implodedActivityIds) {
      $activityIds = explode(',', $implodedActivityIds);
      $errors[] = sprintf(
        'Vypravěč %s je v čase od %s do %s na %s %s.',
        $this->descriveUserById((int)$conflictingStorytellerId),
        $zacatek->formatCasStandard(),
        $konec->formatCasStandard(),
        count($activityIds) === 1
          ? 'aktivitě'
          : 'aktivitách',
        implode(' a ', array_map(function ($activityId) {
          return $this->describeActivityById((int)$activityId);
        }, $activityIds))
      );
    }
    $errors[] = sprintf(
      "Aktivita '%s' byla vynechána.",
      $currentActivityId
        ? $this->describeActivityById($currentActivityId)
        : $values[AktivitaSqlSloupce::URL_AKCE] || $values[AktivitaSqlSloupce::NAZEV_AKCE] || var_export($values, true)
    );
    return $this->error(implode('<br>', $errors));
  }

  private function createRangeDates(?string $zacatekString, ?string $konecString): ?array {
    if ($zacatekString === null && $konecString === null) {
      // nothing to check, we do not know the activity time
      return null;
    }
    $zacatek = $zacatekString
      ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $zacatekString)
      : null;
    $konec = $konecString
      ? DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $konecString)
      : null;
    if (!$zacatek) {
      $zacatek = (clone $konec)->modify('-1 hour');
    }
    if (!$konec) {
      $konec = (clone $zacatek)->modify('+1 hour');
    }
    return ['start' => $zacatek, 'end' => $konec];
  }

  private function checkLocationByAccessibility(
    ?int $locationId,
    ?string $zacatekString,
    ?string $konecString,
    ?int $currentActivityId,
    array $values
  ): array {
    if ($locationId === null) {
      return $this->success(null);
    }
    $rangeDates = $this->createRangeDates($zacatekString, $konecString);
    if (!$rangeDates) {
      return $this->success(true);
    }
    /** @var DateTimeCz $zacatek */
    /** @var DateTimeCz $konec */
    ['start' => $zacatek, 'end' => $konec] = $rangeDates;
    $locationOccupyingActivityId = dbOneCol(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE akce_seznam.lokace = $1
AND akce_seznam.zacatek >= $2
AND akce_seznam.konec <= $3
AND CASE
    WHEN $4 IS NULL THEN TRUE
    ELSE akce_seznam.id_akce != $4
    END
LIMIT 1
SQL
      , [$locationId, $zacatek->format(DateTimeCz::FORMAT_DB), $konec->format(DateTimeCz::FORMAT_DB), $currentActivityId]
    );
    if (!$locationOccupyingActivityId) {
      return $this->success($locationId);
    }
    return $this->warning(sprintf(
      'Místnost %s je někdy mezi %s a %s již zabraná aktivitou %s. Aktivita %s byla nahrána <strong>bez</strong> místnosti.',
      $this->describeLocationById($locationId),
      $zacatek->formatCasStandard(),
      $konec->formatCasStandard(),
      $this->describeActivityById((int)$locationOccupyingActivityId),
      $currentActivityId
        ? $this->describeActivityById($currentActivityId)
        : $values[AktivitaSqlSloupce::URL_AKCE] || $values[AktivitaSqlSloupce::NAZEV_AKCE] || var_export($values, true)
    ));
  }

  private function describeLocationById(int $locationId): string {
    $lokace = \Lokace::zId($locationId);
    return sprintf('%s (%s)', $lokace->nazev(), $lokace->id());
  }

  private function descriveUserById(int $userId): string {
    $uzivatel = \Uzivatel::zId($userId);
    return sprintf('%s (%s)', $uzivatel->jmenoNick(), $uzivatel->id());
  }

  private function describeActivityById(int $activityId): string {
    $aktivita = \Aktivita::zId($activityId);
    return $this->describeActivity($aktivita);
  }

  private function describeActivity(\Aktivita $aktivita): string {
    return $this->getLinkToActivity($aktivita);
  }

  private function saveActivity(array $values, ?string $longAnnotation, array $storytellersIds, array $tagIds, ?\Aktivita $originalActivity): array {
    try {
      return $this->success(\Aktivita::uloz($values, $longAnnotation, $storytellersIds, $tagIds));
    } catch (\Exception $exception) {
      $this->logovac->zaloguj($exception);
      return $this->error(sprintf('Nepodařilo se uložit aktivitu %s', $this->describeActivityByValues($values, $originalActivity)));
    }
  }

  private function validateValues(array $activityValues, ?\Aktivita $aktivita): array {
    $sanitizedValues = [];
    if ($aktivita) {
      $sanitizedValues = $aktivita->rawDb();
      // remove values originating in another tables
      $sanitizedValues = array_intersect_key(
        $sanitizedValues,
        array_fill_keys(AktivitaSqlSloupce::vsechnySloupce(), true)
      );
    }
    $tagIds = null;
    $storytellersIds = null;

    ['success' => $programLineId, 'error' => $programLineIdError] = $this->getValidatedProgramLineId($activityValues, $aktivita);
    if ($programLineIdError) {
      return $this->error($programLineIdError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TYP] = $programLineId;

    ['success' => $activityName, 'error' => $activityNameError] = $this->getValidatedActivityName($activityValues, $aktivita);
    if ($activityNameError) {
      return $this->error($activityNameError);
    }
    $sanitizedValues[AktivitaSqlSloupce::NAZEV_AKCE] = $activityName;

    ['success' => $activityUrl, 'error' => $activityUrlError] = $this->getValidatedUrl($activityValues, $aktivita);
    if ($activityUrlError) {
      return $this->error($activityUrlError);
    }
    $sanitizedValues[AktivitaSqlSloupce::URL_AKCE] = $activityUrl;

    ['success' => $shortAnnotation, 'error' => $shortAnnotationError] = $this->getValidatedShortAnnotation($activityValues, $aktivita);
    if ($shortAnnotationError) {
      return $this->error($shortAnnotationError);
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS_KRATKY] = $shortAnnotation;

    ['success' => $tagIds, 'error' => $tagIdsError] = $this->getValidatedTagIds($activityValues, $aktivita);
    if ($tagIdsError) {
      return $this->error($tagIdsError);
    }

    ['success' => $longAnnotation, 'error' => $longAnnotationError] = $this->getValidatedLongAnnotation($activityValues, $aktivita);
    if ($longAnnotationError) {
      return $this->error($longAnnotationError);
    }

    ['success' => $activityStart, 'error' => $activityStartError] = $this->getValidatedStart($activityValues, $aktivita);
    if ($activityStartError) {
      return $this->error($activityStartError);
    }
    $sanitizedValues[AktivitaSqlSloupce::ZACATEK] = $activityStart;

    ['success' => $activityEnd, 'error' => $activityEndError] = $this->getValidatedEnd($activityValues, $aktivita);
    if ($activityEndError) {
      return $this->error($activityEndError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KONEC] = $activityEnd;

    ['success' => $programLocationId, 'error' => $programLocationIdError] = $this->getValidatedProgramLocationId($activityValues, $aktivita);
    if ($programLocationIdError) {
      return $this->error($programLocationIdError);
    }
    $sanitizedValues[AktivitaSqlSloupce::LOKACE] = $programLocationId;

    ['success' => $storytellersIds, 'error' => $storytellersIdsError] = $this->getValidatedStorytellersIds($activityValues, $aktivita);
    if ($storytellersIdsError) {
      return $this->error($storytellersIdsError);
    }

    ['success' => $unisexCapacity, 'error' => $unisexCapacityError] = $this->getValidatedUnisexCapacity($activityValues, $aktivita);
    if ($unisexCapacityError) {
      return $this->error($unisexCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA] = $unisexCapacity;

    ['success' => $menCapacity, 'error' => $menCapacityError] = $this->getValidatedMenCapacity($activityValues, $aktivita);
    if ($menCapacityError) {
      return $this->error($menCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_M] = $menCapacity;

    ['success' => $womenCapacity, 'error' => $womenCapacityError] = $this->getValidatedWomenCapacity($activityValues, $aktivita);
    if ($womenCapacityError) {
      return $this->error($womenCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_F] = $womenCapacity;

    ['success' => $forTeam, 'error' => $forTeamError] = $this->getValidatedForTeam($activityValues, $aktivita);
    if ($forTeamError) {
      return $this->error($forTeamError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAMOVA] = $forTeam;

    ['success' => $minimalTeamCapacity, 'error' => $minimalTeamCapacityError] = $this->getValidatedMinimalTeamCapacity($activityValues, $aktivita);
    if ($minimalTeamCapacityError) {
      return $this->error($minimalTeamCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MIN] = $minimalTeamCapacity;

    ['success' => $maximalTeamCapacity, 'error' => $maximalTeamCapacityError] = $this->getValidatedMaximalTeamCapacity($activityValues, $aktivita);
    if ($maximalTeamCapacityError) {
      return $this->error($maximalTeamCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MAX] = $maximalTeamCapacity;

    ['success' => $price, 'error' => $priceError] = $this->getValidatedPrice($activityValues, $aktivita);
    if ($priceError) {
      return $this->error($priceError);
    }
    $sanitizedValues[AktivitaSqlSloupce::CENA] = $price;

    ['success' => $withoutDiscount, 'error' => $withoutDiscountError] = $this->getValidatedWithoutDiscount($activityValues, $aktivita);
    if ($withoutDiscountError) {
      return $this->error($withoutDiscountError);
    }
    $sanitizedValues[AktivitaSqlSloupce::BEZ_SLEVY] = $withoutDiscount;

    ['success' => $equipment, 'error' => $equipmentError] = $this->getValidatedEquipment($activityValues, $aktivita);
    if ($equipmentError) {
      return $this->error($equipmentError);
    }
    $sanitizedValues[AktivitaSqlSloupce::VYBAVENI] = $equipment;

    ['success' => $stateId, 'error' => $stateIdError] = $this->getValidatedStateId($activityValues, $aktivita);
    if ($stateIdError) {
      return $this->error($stateIdError);
    }
    $sanitizedValues[AktivitaSqlSloupce::STAV] = $stateId;

    ['success' => $year, 'error' => $yearError] = $this->getValidatedYear($activityValues, $aktivita);
    if ($yearError) {
      return $this->error($yearError);
    }
    $sanitizedValues[AktivitaSqlSloupce::ROK] = $year;

    return $this->success(['values' => $sanitizedValues, 'longAnnotation' => $longAnnotation, 'storytellersIds' => $storytellersIds, 'tagIds' => $tagIds]);
  }

  private function getValidatedStateId(array $activityValues, ?\Aktivita $aktivita): array {
    $stateValue = $activityValues[ExportAktivitSloupce::STAV] ?? null;
    if ((string)$stateValue === '') {
      return $this->success($aktivita && $aktivita->stav()
        ? $aktivita->stav()->id()
        : null
      );
    }
    $state = $this->getStateFromValue((string)$stateValue);
    if ($state) {
      return $this->success($state->id());
    }
    return $this->error(sprintf("Neznámý stav '%s'", $stateValue));
  }

  private function getStateFromValue(string $StateValue): ?\Stav {
    $StateInt = (int)$StateValue;
    if ($StateInt > 0) {
      return $this->getStateById($StateInt);
    }
    return $this->getStateByName($StateValue);
  }

  private function getStateById(int $id): ?\Stav {
    return $this->getStatesCache()['id'][$id] ?? null;
  }

  private function getStateByName(string $name): ?\Stav {
    return $this->getStatesCache()['keyFromName'][self::toUnifiedKey(mb_substr($name, 0, 3, 'UTF-8'), [])] ?? null;
  }

  private function getStatesCache(): array {
    if (!$this->StatesCache) {
      $this->StatesCache = ['id' => [], 'keyFromName' => []];
      $States = \Stav::zVsech();
      foreach ($States as $State) {
        $this->StatesCache['id'][$State->id()] = $State;
        $keyFromName = self::toUnifiedKey(mb_substr($State->nazev(), 0, 3, 'UTF-8'), array_keys($this->StatesCache['keyFromName']));
        $this->StatesCache['keyFromName'][$keyFromName] = $State;
      }
    }
    return $this->StatesCache;
  }

  private function getValidatedEquipment(array $activityValues, ?\Aktivita $aktivita): array {
    $equipmentValue = $activityValues[ExportAktivitSloupce::VYBAVENI] ?? null;
    if ((string)$equipmentValue === '') {
      return $this->success($aktivita
        ? $aktivita->vybaveni()
        : ''
      );
    }
    return $this->success($equipmentValue);
  }

  private function getValidatedMinimalTeamCapacity(array $activityValues, ?\Aktivita $aktivita): array {
    $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$minimalTeamCapacityValue === '') {
      return $this->success($aktivita
        ? $aktivita->tymMinKapacita()
        : 0
      );
    }
    $minimalTeamCapacity = (int)$minimalTeamCapacityValue;
    if ($minimalTeamCapacity > 0) {
      return $this->success($minimalTeamCapacity);
    }
    if ((string)$minimalTeamCapacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná minimální kapacita týmu '%s'. Očekáváme celé kladné číslo.", $minimalTeamCapacityValue));
  }

  private function getValidatedMaximalTeamCapacity(array $activityValues, ?\Aktivita $aktivita): array {
    $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$maximalTeamCapacityValue === '') {
      return $this->success($aktivita
        ? $aktivita->tymMaxKapacita()
        : 0
      );
    }
    $maximalTeamCapacity = (int)$maximalTeamCapacityValue;
    if ($maximalTeamCapacity > 0) {
      return $this->success($maximalTeamCapacity);
    }
    if ((string)$maximalTeamCapacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná maximální kapacita týmu '%s'. Očekáváme celé kladné číslo.", $maximalTeamCapacityValue));
  }

  private function getValidatedForTeam(array $activityValues, ?\Aktivita $aktivita): array {
    $forTeamValue = $activityValues[ExportAktivitSloupce::JE_TYMOVA] ?? null;
    if ((string)$forTeamValue === '') {
      return $this->success(
        $aktivita && $aktivita->tymova()
          ? 1
          : 0
      );
    }
    $forTeam = $this->parseBoolean($forTeamValue);
    if ($forTeam !== null) {
      return $this->success(
        $forTeam
          ? 1
          : 0
      );
    }
    return $this->error(sprintf("Podivný zápis, zda je aktivita týmová: '%s'. Očekáváme pouze 1, 0, ano, ne.", $forTeamValue));
  }

  private function getValidatedStorytellersIds(array $activityValues, ?\Aktivita $aktivita): array {
    $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? null;
    if (!$storytellersString) {
      return $this->success($aktivita
        ? $aktivita->getOrganizatoriIds()
        : []
      );
    }
    $storytellersIds = [];
    $invalidStorytellersValues = [];
    $storytellersValues = array_map('trim', explode(',', $storytellersString));
    foreach ($storytellersValues as $storytellerValue) {
      $storyteller = $this->getStorytellerFromValue($storytellerValue);
      if (!$storyteller) {
        $invalidStorytellersValues[] = $storytellerValue;
      } else {
        $storytellersIds[] = $storyteller->id();
      }
    }
    if ($invalidStorytellersValues) {
      $this->error(
        sprintf('Neznámí vypravěči %s', implode(',', array_map(static function (string $invalidStorytellerValue) {
          return "'$invalidStorytellerValue'";
        }, $invalidStorytellersValues)))
      );
    }
    return $this->success($storytellersIds);
  }

  private function getStorytellerFromValue(string $storytellerValue): ?\Uzivatel {
    $storytellerInt = (int)$storytellerValue;
    if ($storytellerInt > 0) {
      return $this->getStorytellerById($storytellerInt);
    }
    return $this->getStorytellerByEmail($storytellerValue)
      ?? $this->getStorytellerByName($storytellerValue)
      ?? $this->getStorytellerByNick($storytellerValue);
  }

  private function getStorytellerById(int $id): ?\Uzivatel {
    return $this->getStorytellersCache()['id'][$id] ?? null;
  }

  private function getStorytellerByEmail(string $email): ?\Uzivatel {
    if (strpos($email, '@') === false) {
      return null;
    }
    $key = self::toUnifiedKey($email, [], self::UNIFY_UP_TO_SPACES);
    return $this->getStorytellersCache()['keyFromEmail'][$key] ?? null;
  }

  private function getStorytellerByName(string $name): ?\Uzivatel {
    $key = self::toUnifiedKey($name, [], $this->keyUnifyDepth['storytellers']['fromName']);
    return $this->getStorytellersCache()['keyFromName'][$key] ?? null;
  }

  private function getStorytellerByNick(string $nick): ?\Uzivatel {
    $key = self::toUnifiedKey($nick, [], $this->keyUnifyDepth['storytellers']['fromNick']);
    return $this->getStorytellersCache()['keyFromNick'][$key] ?? null;
  }

  private function getStorytellersCache(): array {
    if (!$this->storytellersCache) {
      $this->storytellersCache = ['id' => [], 'keyFromEmail' => [], 'keyFromName' => [], 'keyFromNick' => []];
      $this->keyUnifyDepth['storytellers'] = ['fromName' => self::UNIFY_UP_TO_LETTERS, 'fromNick' => self::UNIFY_UP_TO_LETTERS];

      $storytellers = \Uzivatel::organizatori();

      foreach ($storytellers as $storyteller) {
        $this->storytellersCache['id'][$storyteller->id()] = $storyteller;
        $keyFromEmail = self::toUnifiedKey($storyteller->mail(), array_keys($this->storytellersCache['keyFromEmail']), self::UNIFY_UP_TO_SPACES);
        $this->storytellersCache['keyFromEmail'][$keyFromEmail] = $storyteller;
      }

      for ($nameKeyUnifyDepth = $this->keyUnifyDepth['storytellers']['fromName']; $nameKeyUnifyDepth >= 0; $nameKeyUnifyDepth--) {
        $keyFromNameCache = [];
        foreach ($storytellers as $storyteller) {
          $name = $storyteller->jmeno();
          if ($name === '') {
            continue;
          }
          try {
            $keyFromCivilName = self::toUnifiedKey($name, array_keys($this->storytellersCache['keyFromName']), $nameKeyUnifyDepth);
            $keyFromNameCache[$keyFromCivilName] = $storyteller;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storytellersCache['keyFromName'] = $keyFromNameCache;
        $this->keyUnifyDepth['storytellers']['fromName'] = min($this->keyUnifyDepth['storytellers']['fromName'], $nameKeyUnifyDepth);
        break; // all names converted to unified and unique keys
      }

      for ($nickKeyUnifyDepth = $this->keyUnifyDepth['storytellers']['fromNick']; $nickKeyUnifyDepth >= 0; $nickKeyUnifyDepth--) {
        $keyFromNickCache = [];
        foreach ($storytellers as $storyteller) {
          $nick = $storyteller->nick();
          if ($nick === '') {
            continue;
          }
          try {
            $keyFromNick = self::toUnifiedKey($nick, array_keys($this->storytellersCache['keyFromNick']), $nickKeyUnifyDepth);
            $keyFromNickCache[$keyFromNick] = $storyteller;
            // if unification was too aggressive and we had to lower level of depth / lossy compression, we have to store the lowest level for later picking-up values from cache
          } catch (DuplicatedUnifiedKeyException $unifiedKeyException) {
            continue 2; // lower key depth
          }
        }
        $this->storytellersCache['keyFromNick'] = $keyFromNickCache;
        $this->keyUnifyDepth['storytellers']['fromNick'] = min($this->keyUnifyDepth['storytellers']['fromNick'], $nickKeyUnifyDepth);
        break; // all nicks converted to unified and unique keys
      }
    }
    return $this->storytellersCache;
  }

  private function getValidatedLongAnnotation(array $activityValues, ?\Aktivita $aktivita): array {
    if (!empty($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE])) {
      return $this->success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE]);
    }
    return $this->success($aktivita
      ? $aktivita->popis()
      : ''
    );
  }

  private function getValidatedTagIds(array $activityValues, ?\Aktivita $aktivita): array {
    $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
    if ($tagsString === '' && $aktivita) {
      $tagIds = [];
      $invalidTagsValues = [];
      foreach ($aktivita->tagy() as $tagValue) {
        $tag = $this->getTagFromValue($tagValue);
        if (!$tag) {
          $invalidTagsValues[] = $tagValue;
        } else {
          $tagIds[] = $tag->id();
        }
      }
      if ($invalidTagsValues) {
        trigger_error(
          E_USER_WARNING,
          sprintf('There are some strange tags coming from activity %s, which are unknown %s', $aktivita->id(), implode(',', $invalidTagsValues))
        );
      }
      return $this->success($tagIds);
    }
    $tagIds = [];
    $invalidTagsValues = [];
    $tagsValues = array_map('trim', explode(',', $tagsString));
    foreach ($tagsValues as $tagValue) {
      $tag = $this->getTagFromValue($tagValue);
      if (!$tag) {
        $invalidTagsValues[] = $tagValue;
      } else {
        $tagIds[] = $tag->id();
      }
    }
    if ($invalidTagsValues) {
      $this->error(
        sprintf('Neznámé tagy %s', implode(',', array_map(static function (string $invalidTagValue) {
          return "'$invalidTagValue'";
        }, $invalidTagsValues)))
      );
    }
    return $this->success($tagIds);
  }

  private function getTagFromValue(string $tagValue): ?\Tag {
    $tagInt = (int)$tagValue;
    if ($tagInt > 0) {
      return $this->getTagById($tagInt);
    }
    return $this->getTagByName($tagValue);
  }

  private function getTagById(int $id): ?\Tag {
    return $this->getTagsCache()['id'][$id] ?? null;
  }

  private function getTagByName(string $name): ?\Tag {
    return $this->getTagsCache()['keyFromName'][self::toUnifiedKey($name, [])] ?? null;
  }

  private function getTagsCache(): array {
    if (!$this->tagsCache) {
      $this->tagsCache = ['id' => [], 'keyFromName' => []];
      $tags = \Tag::zVsech();
      foreach ($tags as $tag) {
        $this->tagsCache['id'][$tag->id()] = $tag;
        $keyFromName = self::toUnifiedKey($tag->nazev(), array_keys($this->tagsCache['keyFromName']));
        $this->tagsCache['keyFromName'][$keyFromName] = $tag;
      }
    }
    return $this->tagsCache;
  }

  private function getValidatedShortAnnotation(array $activityValues, ?\Aktivita $aktivita): array {
    if (!empty($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE])) {
      return $this->success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]);
    }
    return $this->success($aktivita
      ? $aktivita->kratkyPopis()
      : ''
    );
  }

  private function getValidatedYear(array $activityValues, \Aktivita $aktivita): array {
    if (!$aktivita) {
      return $this->success($this->currentYear);
    }
    $year = $aktivita->zacatek()
      ? (int)$aktivita->zacatek()->format('Y')
      : null;
    if (!$year) {
      $year = $aktivita->konec()
        ? (int)$aktivita->konec()->format('Y')
        : null;
    }
    if ($year) {
      if ($year !== $this->currentYear) {
        $this->error(
          sprintf('Aktivita %s je pro ročník %d, ale teď je ročník %d', $this->describeActivity($aktivita), $year, $this->currentYear)
        );
      }
      return $this->success($year);
    }
    return $this->success($this->currentYear);
  }

  private function getValidatedWithoutDiscount(array $activityValues, ?\Aktivita $aktivita): array {
    $withoutDiscountValue = $activityValues[ExportAktivitSloupce::BEZ_SLEV] ?? null;
    if ((string)$withoutDiscountValue === '') {
      return $this->success($aktivita && $aktivita->bezSlevy()
        ? 1
        : 0
      );
    }
    $withoutDiscount = $this->parseBoolean($withoutDiscountValue);
    if ($withoutDiscount !== null) {
      return $this->success(
        $withoutDiscount
          ? 1
          : 0
      );
    }
    return $this->error(sprintf("Podivný zápis 'bez slevy': '%s'. Očekáváme pouze 1, 0, ano, ne.", $withoutDiscountValue));
  }

  private function parseBoolean($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    switch (substr((string)$value, 0, 1)) {
      case '0' :
      case 'n' :
      case 'f' :
        return false;
      case '1' :
      case 'a' :
      case 'y' :
        return true;
      default :
        return null;
    }
  }

  private function getValidatedUnisexCapacity(array $activityValues, ?\Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_UNISEX] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita
        ? $aktivita->getKapacitaUnisex()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná unisex kapacita '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedMenCapacity(array $activityValues, ?\Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_MUZI] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita
        ? $aktivita->getKapacitaMuzu()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná kapacita mužů '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedWomenCapacity(array $activityValues, ?\Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_ZENY] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita
        ? $aktivita->getKapacitaZen()
        : null
      );
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná kapacita žen '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedPrice(array $activityValues, ?\Aktivita $aktivita): array {
    $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
    if ((string)$priceValue === '') {
      return $this->success($aktivita
        ? $aktivita->cenaZaklad()
        : 0.0
      );
    }
    $priceFloat = (float)$priceValue;
    if ($priceFloat !== 0.0) {
      return $this->success($priceFloat);
    }
    if ((string)$priceFloat === '0' || (string)$priceFloat === '0.0') {
      return $this->success(0.0);
    }
    return $this->error(sprintf("Podivná cena aktivity '%s'. Očekáváme číslo.", $priceValue));
  }

  private function getValidatedProgramLocationId(array $activityValues, ?\Aktivita $aktivita): array {
    $locationValue = $activityValues[ExportAktivitSloupce::MISTNOST] ?? null;
    if (!$locationValue) {
      if (!$aktivita) {
        return $this->success(null);
      }
      return $this->success($aktivita->lokaceId());
    }
    $location = $this->getProgramLocationFromValue((string)$locationValue);
    if ($location) {
      return $this->success($location->id());
    }
    return $this->error(sprintf("Neznámá lokace '%s'", $locationValue));
  }

  private function getProgramLocationFromValue(string $programLocationValue): ?\Lokace {
    $programLocationInt = (int)$programLocationValue;
    if ($programLocationInt > 0) {
      return $this->getProgramLocationById($programLocationInt);
    }
    return $this->getProgramLocationByName($programLocationValue);
  }

  private function getProgramLocationById(int $id): ?\Lokace {
    return $this->getProgramLocationsCache()['id'][$id] ?? null;
  }

  private function getProgramLocationByName(string $name): ?\Lokace {
    return $this->getProgramLocationsCache()['keyFromName'][self::toUnifiedKey($name, [])] ?? null;
  }

  private function getProgramLocationsCache(): array {
    if (!$this->programLocationsCache) {
      $this->programLocationsCache = ['id' => [], 'keyFromName' => []];
      $locations = \Lokace::zVsech();
      foreach ($locations as $location) {
        $this->programLocationsCache['id'][$location->id()] = $location;
        $keyFromName = self::toUnifiedKey($location->nazev(), array_keys($this->programLocationsCache['keyFromName']));
        $this->programLocationsCache['keyFromName'][$keyFromName] = $location;
      }
    }
    return $this->programLocationsCache;
  }

  private function getValidatedStart(array $activityValues, ?\Aktivita $aktivita): array {
    $beginning = $activityValues[ExportAktivitSloupce::ZACATEK] ?? null;
    if (!$beginning) {
      if ($aktivita) {
        return $this->success(null);
      }
      $beginningObject = $aktivita->zacatek();
      return $this->success($beginningObject
        ? $beginningObject->formatDb()
        : null
      );
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return $this->error(
        sprintf(
          'U aktivity %s je sice začátek (%s), ale chybí u ní den.',
          $this->describeActivityByValues($activityValues, $aktivita),
          $activityValues[ExportAktivitSloupce::ZACATEK]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK]);
  }

  private function getValidatedEnd(array $activityValues, ?\Aktivita $aktivita): array {
    $activityEnd = $activityValues[ExportAktivitSloupce::KONEC] ?? null;
    if (!$activityEnd) {
      if (!$aktivita) {
        return $this->success(null);
      }
      $activityEndObject = $aktivita->konec();
      return $this->success($activityEndObject
        ? $activityEndObject->formatDb()
        : null
      );
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return $this->error(
        sprintf(
          'U aktivity %s je sice konec (%s), ale chybí u ní den.',
          $this->describeActivityByValues($activityValues, $aktivita),
          $activityValues[ExportAktivitSloupce::KONEC]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::KONEC]);
  }

  private function describeActivityByValues(array $activityValues, ?\Aktivita $aktivita): string {
    $id = $activityValues[ExportAktivitSloupce::ID_AKTIVITY]
      ?? $aktivita
        ? $aktivita->id()
        : '';
    $nazev = $activityValues[ExportAktivitSloupce::NAZEV]
      ?? $aktivita
        ? $aktivita->nazev()
        : '';
    if ($id && $nazev) {
      return "$nazev ($id)";
    }
    $url = $activityValues[ExportAktivitSloupce::URL]
      ?? $aktivita
        ? $aktivita->urlId()
        : '';
    if ($nazev && $url) {
      return "$nazev s URL '$url'";
    }
    if ($nazev) {
      return $nazev;
    }
    $kratkaAnotace = $activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]
      ?? $aktivita
        ? $aktivita->kratkyPopis()
        : '';
    return $kratkaAnotace ?: "'bez názvu'";
  }

  private function createDateTimeFromRangeBorder(int $year, string $dayName, string $hoursAndMinutes): array {
    try {
      $date = DateTimeGamecon::denKolemZacatkuGameconuProRok($dayName, $year);
    } catch (\Exception $exception) {
      return $this->error(sprintf("Nepodařilo se vytvořit datum z roku %d, dne '%s' a času '%s'. Chybný formát datumu. Detail: %s", $year, $dayName, $hoursAndMinutes, $exception->getMessage()));
    }

    if (!preg_match('~^(?<hours>\d+)(\s*:\s*(?<minutes>\d+))?$~', $hoursAndMinutes, $timeMatches)) {
      return $this->error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát času '%s'.", $year, $dayName, $hoursAndMinutes, $hoursAndMinutes));
    }
    $hours = (int)$timeMatches['hours'];
    $minutes = (int)($timeMatches['minutes'] ?? 0);
    $dateTime = $date->setTime($hours, $minutes, 0, 0);
    if (!$dateTime) {
      return $this->error(sprintf("Nepodařilo se nastavit čas podle roku %d, dne '%s' a času '%s'. Chybný formát.", $year, $dayName, $hoursAndMinutes));
    }
    return $this->success($dateTime->formatDb());
  }

  private function getValidatedUrl(array $activityValues, ?\Aktivita $aktivita): array {
    $activityUrl = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if (!$activityUrl) {
      if ($aktivita) {
        return $this->success($aktivita->urlId());
      }
      if (empty($activityValues[ExportAktivitSloupce::NAZEV])) {
        return $this->error(sprintf('Nová aktivita %s nemá ani URL, ani název, ze kterého by URL šlo vytvořit.', $this->describeActivityByValues($activityValues, $aktivita)));
      }
      $activityUrl = $this->createUrl($activityValues[ExportAktivitSloupce::NAZEV]);
    }
    $activityUrl = $this->createUrl($activityUrl);
    $occupiedByActivities = dbFetchAll(<<<SQL
SELECT id_akce, nazev_akce, patri_pod
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2
SQL
      ,
      [$activityUrl, $this->currentYear]
    );
    if ($occupiedByActivities) {
      foreach ($occupiedByActivities as $occupiedByActivity) {
        $occupiedByActivityId = (int)$occupiedByActivity['id_akce'];
        $patriPod = $occupiedByActivity['patri_pod']
          ? (int)$occupiedByActivity['patri_pod']
          : null;
        if ($occupiedByActivityId
          && (!$aktivita
            || ($occupiedByActivityId !== $aktivita->id() && (!$patriPod || $patriPod !== $aktivita->patriPod())))
        ) {
          return $this->error(sprintf(
            "URL '%s' %saktivity %s už je obsazena aktivitou %s",
            empty($activityValues[ExportAktivitSloupce::URL])
              ? '(odhadnutá z názvu) '
              : '',
            $activityUrl,
            $this->describeActivityByValues($activityValues, $aktivita), $this->describeActivityById($occupiedByActivity['id_akce'])
          ));
        }
      }
    }
    return $this->success($activityUrl);
  }

  private function createUrl(string $value): string {
    $sanitized = strtolower(odstranDiakritiku($value));
    return preg_replace('~[^a-z]+~', '-', $sanitized);
  }

  private function getValidatedActivityName(array $activityValues, ?\Aktivita $aktivita): array {
    $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
    if (!$activityNameValue) {
      return $aktivita
        ? $this->success($aktivita->nazev())
        : $this->error(sprintf('Chybí název aktivity pro %s', $this->describeActivityByValues($activityValues, $aktivita)));
    }
    $occupiedByActivityId = dbOneCol('SELECT id_akce FROM akce_seznam WHERE nazev_akce = $1 AND rok = $2', [$activityNameValue, $this->currentYear]);
    if ($occupiedByActivityId && (!$aktivita || (int)$occupiedByActivityId !== $aktivita->id())) {
      return $this->error(sprintf("Název aktivity '%s' už je obsazený aktivitou %s", $activityNameValue, $this->describeActivityById((int)$occupiedByActivityId)));
    }
    return $this->success($activityNameValue);
  }

  private function getValidatedProgramLineId(array $activityValues, ?\Aktivita $aktivita): array {
    $programLineValue = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if ((string)$programLineValue === '') {
      return $aktivita
        ? $this->success($aktivita->typId())
        : $this->error(sprintf("Chybí programová linie u aktivity %s", $this->describeActivityByValues($activityValues, null)));
    }
    $programLine = $this->getProgramLineFromValue((string)$programLineValue);
    return $programLine
      ? $this->success($programLine->id())
      : $this->error(sprintf("Neznámá programová linie '%s'", $programLineValue));
  }

  private function getProgramLineFromValue(string $programLineValue): ?\Typ {
    $programLineInt = (int)$programLineValue;
    if ($programLineInt > 0) {
      return $this->getProgramLineById($programLineInt);
    }
    return $this->getProgramLineByName($programLineValue);
  }

  private function getProgramLineById(int $id): ?\Typ {
    return $this->getProgramLinesCache()['id'][$id] ?? null;
  }

  private function getProgramLineByName(string $name): ?\Typ {
    return $this->getProgramLinesCache()['keyFromName'][self::toUnifiedKey($name, [])] ?? null;
  }

  private function getProgramLinesCache(): array {
    if (!$this->programLinesCache) {
      $this->programLinesCache = ['id' => [], 'keyFromName' => []];
      $programLines = \Typ::zVsech();
      foreach ($programLines as $programLine) {
        $this->programLinesCache['id'][$programLine->id()] = $programLine;
        $keyFromName = self::toUnifiedKey($programLine->nazev(), array_keys($this->programLinesCache['keyFromName']));
        $this->programLinesCache['keyFromName'][$keyFromName] = $programLine;
      }
    }
    return $this->programLinesCache;
  }

  private const UNIFY_UP_TO_WHITESPACES = 1;
  private const UNIFY_UP_TO_CASE = 2;
  private const UNIFY_UP_TO_SPACES = 3;
  private const UNIFY_UP_TO_WORD_CHARACTERS = 4;
  private const UNIFY_UP_TO_DIACRITIC = 5;
  private const UNIFY_UP_TO_LETTERS = 6;

  private static function toUnifiedKey(
    string $value,
    array $occupiedKeys,
    int $unifyDepth = self::UNIFY_UP_TO_LETTERS
  ): string {
    $unifiedKey = self::createUnifiedKey($value, $unifyDepth);
    if (array_key_exists($unifiedKey, $occupiedKeys)) {
      throw new DuplicatedUnifiedKeyException(
        sprintf(
          "Can not create unified key from '%s' as resulting key '%s' already exists: %s",
          $value,
          $unifiedKey,
          implode(';', array_map(static function (string $occupiedKey) {
            return "'$occupiedKey'";
          }, $occupiedKeys))
        ),
        $unifiedKey
      );
    }
    return $unifiedKey;
  }

  private static function createUnifiedKey(string $value, int $depth): string {
    if ($depth <= 0) {
      return $value;
    }
    $value = preg_replace('~\s+~', ' ', $value);
    if ($depth === self::UNIFY_UP_TO_WHITESPACES) {
      return $value;
    }
    $value = mb_strtolower($value, 'UTF-8');
    if ($depth === self::UNIFY_UP_TO_CASE) {
      return $value;
    }
    $value = (string)str_replace(' ', '', $value);
    if ($depth === self::UNIFY_UP_TO_SPACES) {
      return $value;
    }
    $value = preg_replace('~\W~u', '', $value);
    if ($depth === self::UNIFY_UP_TO_WORD_CHARACTERS) {
      return $value;
    }
    $value = odstranDiakritiku($value);
    if ($depth === self::UNIFY_UP_TO_DIACRITIC) {
      return $value;
    }
    $value = preg_replace('~[^a-z]~', '', $value);
    if ($depth === self::UNIFY_UP_TO_LETTERS) {
      return $value;
    }
    return $value;
  }

  private function getLinkToActivity(\Aktivita $aktivita): string {
    return sprintf('<a target="_blank" href="%s%d">%s</a>', $this->editActivityUrlSkeleton, $aktivita->id(), $this->describeActivity($aktivita));
  }
}
