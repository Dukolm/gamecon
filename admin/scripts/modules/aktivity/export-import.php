<?php

namespace Gamecon\Admin\Modules\Aktivity;

use Gamecon\Admin\Modules\Aktivity\Export\ExporterAktivit;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleApiClient;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiCredentials;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Models\GoogleApiTokenStorage;
use Gamecon\Admin\Modules\Aktivity\Import\ImporterAktivit;
use Gamecon\Mutex\Mutex;
use Gamecon\Vyjimkovac\Logovac;

/**
 * Stránka pro hromadný export aktivit.
 *
 * nazev: Export & Import
 * pravo: 102
 */

if ($_GET['zpet'] ?? '' === 'aktivity') {
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) . '/..');
}

$googleApiCredentials = new GoogleApiCredentials(GOOGLE_API_CREDENTIALS);
/** @type \Uzivatel $u */
$googleApiClient = new GoogleApiClient(
  $googleApiCredentials,
  new GoogleApiTokenStorage($googleApiCredentials->getClientId()),
  $u->id()
);

if (!empty($_GET['flush-authorization'])) {
  $googleApiClient->flushAllAuthorizations();
}

if (isset($_GET['code'])) {
  $googleApiClient->authorizeByCode($_GET['code']);
  oznameni('Spárování s Google bylo úspěšné', false);
  // redirect to remove code from URL and avoid repeated but invalid re-authorization by the same code
  back(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}

/** @noinspection PhpUnusedLocalVariableInspection */
$filtrovatPodleRoku = true;
[$filtr, $razeni] = include __DIR__ . '/_filtr-moznosti.php';
$aktivity = \Aktivita::zFiltru($filtr, $razeni);
$activityTypeIdsFromFilter = array_unique(
  array_map(
    static function (\Aktivita $aktivita) {
      return $aktivita->typId();
    },
    $aktivity
  )
);

$template = new \XTemplate(__DIR__ . '/export-import.xtpl');

$urlNaAktivity = $_SERVER['REQUEST_URI'] . '/..';
$template->assign('urlNaAktivity', $urlNaAktivity);

$googleDriveService = new GoogleDriveService($googleApiClient);
$googleSheetsService = new GoogleSheetsService($googleApiClient, $googleDriveService);

// EXPORT
if (count($activityTypeIdsFromFilter) > 1) {
  $template->parse('export.neniVybranTyp');
} else if (count($activityTypeIdsFromFilter) === 0) {
  $template->parse('export.zadneAktivity');
} else if (count($activityTypeIdsFromFilter) === 1) {
  $activityTypeIdFromFilter = reset($activityTypeIdsFromFilter);

  if (!empty($_POST['export_activity_type_id']) && (int)$_POST['export_activity_type_id'] === (int)$activityTypeIdFromFilter && $googleApiClient->isAuthorized()) {
    $exportAktivit = new ExporterAktivit($u->id(), $googleDriveService, $googleSheetsService);
    $nazevExportovanehoSouboru = $exportAktivit->exportujAktivity($aktivity, (string)($filtr['rok'] ?? ROK));
    oznameni(sprintf("Aktivity byly exportovány do Google sheets pod názvem '%s'", $nazevExportovanehoSouboru));
    exit;
  }
  $template->assign('activityTypeId', $activityTypeIdFromFilter);

  $typAktivity = \Typ::zId($activityTypeIdFromFilter);
  $template->assign('nazevTypu', mb_ucfirst($typAktivity->nazev()) . (($filtr['rok'] ?? ROK) != ROK ? (' ' . $filtr['rok']) : ''));
  $pocetAktivit = count($aktivity);
  $pocetAktivitSlovo = 'aktivit';
  if ($pocetAktivit === 1) {
    $pocetAktivitSlovo = 'aktivitu';
  } elseif ($pocetAktivit > 1 && $pocetAktivit < 5) {
    $pocetAktivitSlovo = 'aktivity';
  }
  $template->assign('pocetAktivit', $pocetAktivit);
  $template->assign('pocetAktivitSlovo', $pocetAktivitSlovo);
  $template->assign('exportDisabled', $googleApiClient->isAuthorized()
    ? ''
    : 'disabled'
  );

  $template->parse('export.exportovat');
}

$template->parse('export');
$template->out('export');

// AUTHOIZACE
if (!$googleApiClient->isAuthorized()) {
  $template->assign('authorizationUrl', $googleApiClient->getAuthorizationUrl());
  $template->parse('autorizace');
  $template->out('autorizace');
}

// IMPORT
$urlNaEditaciAktivity = $urlNaAktivity . '/upravy?aktivitaId=';
if ($googleApiClient->isAuthorized()) {
  if (!empty($_POST['googleSheetId'])) {
    /** @var Logovac $vyjimkovac */
    $importerAktivit = new ImporterAktivit(
      $u->id(),
      $googleDriveService,
      $googleSheetsService,
      ROK,
      new \DateTimeImmutable(),
      $urlNaEditaciAktivity,
      $vyjimkovac,
      Mutex::proAktivity()
    );
    $vysledekImportuAktivit = $importerAktivit->importujAktivity($_POST['googleSheetId']);

    ['importedCount' => $naimportovanoPocet, 'processedFileName' => $nazevImportovanehoSouboru, 'messages' => $messages] = $vysledekImportuAktivit;
    ['successes' => $successes, 'warnings' => $warnings, 'errors' => $errors] = $messages;

    $zprava = sprintf("Bylo naimportováno %d aktivit z Google sheet '%s'", $naimportovanoPocet, $nazevImportovanehoSouboru);
    if ($naimportovanoPocet > 0) {
      oznameni($zprava, false);
    } else {
      chyba($zprava, false);
    }
    $oznameni = \Chyba::vyzvedniHtml();
    $template->assign('oznameni', $oznameni);
    $template->parse('import.oznameni');

    if ($errors) {
      foreach ($errors as $error) {
        $template->assign('error', $error);
        $template->parse('import.errors.error');
      }
      $template->parse('import.errors');
    }
    if ($warnings) {
      foreach ($warnings as $warning) {
        $template->assign('warning', $warning);
        $template->parse('import.warnings.warning');
      }
      $template->parse('import.warnings');
    }
    if ($successes) {
      foreach ($successes as $success) {
        $template->assign('success', $success);
        $template->parse('import.successes.success');
      }
      $template->parse('import.successes');
    }
  }

  $spreadsheets = $googleSheetsService->getAllSpreadsheets();
  foreach ($spreadsheets as $spreadsheet) {
    $template->assign('googleSheetIdEncoded', htmlentities($spreadsheet->getId()));
    $template->assign('nazev', $spreadsheet->getName());
    $template->assign('url', $spreadsheet->getUrl());
    $template->assign('vytvorenoKdy', $spreadsheet->getCreatedAt()->relativni());
    $template->assign('upravenoKdy', $spreadsheet->getModifiedAt()->relativni());
    $template->assign('vytvorenoKdyPresne', $spreadsheet->getCreatedAt()->formatCasStandard());
    $template->assign('upravenoKdyPresne', $spreadsheet->getModifiedAt()->formatCasStandard());
    $template->parse('import.spreadsheets.spreadsheet');
  }
  $template->parse('import.spreadsheets');

  $template->parse('import');
  $template->out('import');
}
