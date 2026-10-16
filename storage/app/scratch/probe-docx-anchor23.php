<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['AccountingController.php:95','BlockingController.php:433','BlockingController.php:336-344',
 'BlockingController.php:559-575','BlockingController.php:571-575','ReferenceDataController.php:272',
 'ReferenceDataController.php:272,285','routes/web.php:141-220','HandleInertiaRequests.php:58-61 (shares',
 'which is never shared with the front end','A toast — unless the message was sent under the info key'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
