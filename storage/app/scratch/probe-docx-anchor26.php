<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['(702 lines)','A new school year must be inserted by SQL','Capacity is enforced when assigning students',
 'One row — Room 101','app/Enums/OfficeId.php has 9 cases and none for 8','DevReferenceDataSeeder.php:638-644',
 'DevReferenceDataSeeder.php:639-644','DevReferenceDataSeeder.php:652-655','DevReferenceDataSeeder.php:44-56',
 'RbacSeeder.php:184','No CRUD screen','Read only as a dropdown source'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
