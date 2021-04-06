<?php
/** @var \Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportResult $vysledekImportuAktivit */

return (static function (\Gamecon\Admin\Modules\Aktivity\Import\ActivitiesImportResult $vysledekImportuAktivit): string {
    $naimportovanoPocet = $vysledekImportuAktivit->getImportedCount();
    $nazevImportovanehoSouboru = $vysledekImportuAktivit->getProcessedFilename();
    $errorMessages = $vysledekImportuAktivit->getErrorMessages();
    $warningMessages = $vysledekImportuAktivit->getErrorLikeAndWarningMessagesExceptErrored();
    $successMessages = $vysledekImportuAktivit->getSuccessMessages();

    $zprava = sprintf("Bylo naimportováno %d aktivit z Google sheet '%s'", $naimportovanoPocet, $nazevImportovanehoSouboru);
    if ($naimportovanoPocet > 0) {
        oznameni($zprava, false);
    } else {
        chyba($zprava, false);
    }
    $flashMessage = \Chyba::vyzvedniHtml();

    $template = new \XTemplate(__DIR__ . '/_import-oznameni.xtpl');
    $template->assign('flashMessage', $flashMessage);
    $template->parse('oznameni.flashMessage');

    $parseImportResultMessages = static function (array $messages, string $mainBlockName, string $itemBlockName, XTemplate $template) {
        $mainItemBlockName = "$mainBlockName.$itemBlockName";
        foreach ($messages as $activityDescription => $singleActivityMessages) {
            if (count($singleActivityMessages) > 1) {
                if ($activityDescription) {
                    $template->assign('nadpis', $activityDescription);
                    $template->parse("$mainItemBlockName.nadpis");
                }
                $template->parseEach($singleActivityMessages, 'message', "$mainItemBlockName.message");
            } else {
                $message = reset($singleActivityMessages);
                $message = $activityDescription . ': ' . $message;
                $template->assign('message', $message);
                $template->parse("$mainItemBlockName.message");
            }
            $template->parse($mainItemBlockName);
        }
        $template->parse($mainBlockName);
    };

    if ($errorMessages) {
        $parseImportResultMessages($errorMessages, 'oznameni.errors', 'error', $template);
    }

    if ($warningMessages) {
        $parseImportResultMessages($warningMessages, 'oznameni.warnings', 'warning', $template);
    }

    if ($successMessages) {
        $parseImportResultMessages($successMessages, 'oznameni.successes', 'success', $template);
    }

    $template->parse('oznameni');
    return $template->text('oznameni');
})($vysledekImportuAktivit);
