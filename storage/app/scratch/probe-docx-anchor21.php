<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['AccountingController.php:95','AccountingController.php:95-99','AccountingController.php:95-98',
 'AccountingController.php:102','PaymentPolicy.php:46-49','AccountingController.php:86-99',
 'AccountingController.php:86-143','AccountingController.php:133-138','AccountingController.php:131',
 'AccountingController.php:138','AccountingController.php:84','AccountingController.php:175-208',
 'ClearancePolicy.php:43-62','PrintService.php:112','PrintService.php:195','PrintService.php:59',
 'the bare integer 2 at AccountingController.php:138','Void” is not a payment status','PaymentStatus has paid'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
