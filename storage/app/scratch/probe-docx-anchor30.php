<?php
$zip = new ZipArchive();
$zip->open('Documentation/EMS Complete Documentation.docx');
$xml = $zip->getFromName('word/document.xml');
$zip->close();
foreach (['Evaluation/Index.jsx:34-36','Evaluation/Index.jsx:23','Registrar/Show.jsx:318','Clearance/Index.jsx:499',
 'Admission/Show.jsx:427','Evaluation/Show.jsx:252','Registrar/Index.jsx:106','PrintLayout.jsx:20','WorkflowStepper.jsx:30',
 'summary control is labelled','the clearance gate is enforced at Phase 5, not here','It no longer does, because the desk asks the clearance itself'] as $f) {
    printf("%2d  %s\n", substr_count($xml, $f), $f);
}
