<?php

namespace Tests\Feature\Enrollment;

use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

/**
 * One enrollment status word, one tone (development prompt §14's consistency clause, matrix gap G-39).
 *
 * `statusLabel.js` holds the single enrollment-tone map, and §28.3's E/U rows record why: four screens each
 * carried their own copy, three of them missing `returnedToEvaluation`, so one record painted differently
 * depending on which desk printed it. The certificate screen was the last hold-out — it built its own
 * six-key map that had never learned either return state, so a record parked at Admission or sent back to the
 * department printed with the same neutral badge as an unknown word.
 *
 * There is no JavaScript test suite, so the pin is a read of the source, the way `YearLevelVocabularyTest`
 * pins the year-level pairing. It asks the two questions that actually fail: does any page re-declare the
 * enrollment vocabulary, and does the certificate page read the shared map instead.
 */
class EnrollmentToneVocabularyTest extends TestCase
{
    /**
     * A map keyed by an enrollment-status word. `proposed`/`confirmed`/`dropped` belong to the
     * enrolled-subject vocabulary and are deliberately not matched here — that is a different question,
     * answered by a different map, and a checker that flags it would be wrong.
     */
    private const REDECLARED = '/[\'"]?(evaluated|assessed|returnedTo[A-Za-z]+)[\'"]?\s*:\s*[\'"][a-zA-Z]+[\'"]/';

    private function pageFiles(): array
    {
        $root = base_path('resources/js/Pages');
        $files = [];
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        foreach ($it as $f) {
            if ($f->isFile() && str_ends_with($f->getFilename(), '.jsx')) {
                $files[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $f->getPathname());
            }
        }
        sort($files);

        return $files;
    }

    #[Test]
    public function no_desk_or_print_page_re_declares_the_enrollment_tone_map(): void
    {
        $offenders = [];
        foreach ($this->pageFiles() as $rel) {
            $src = (string) file_get_contents(base_path($rel));
            if (preg_match(self::REDECLARED, $src, $m)) {
                $offenders[] = $rel.' (key '.$m[1].')';
            }
        }

        $this->assertSame([], $offenders,
            'These screens carry their own enrollment-tone map. The answer lives in '
            .'resources/js/Components/ui/statusLabel.js so one status cannot become two colours.');
    }

    #[Test]
    public function the_certificate_page_reads_the_shared_tone_map_it_was_duplicating(): void
    {
        $src = (string) file_get_contents(base_path('resources/js/Pages/Registrar/PrintCertificate.jsx'));

        $this->assertStringContainsString('enrollmentStatusTone[', $src,
            'The certificate decides its badge tone from the shared map.');
        $this->assertMatchesRegularExpression(
            '/import \{[^}]*\benrollmentStatusTone\b[^}]*\} from \'@\/Components\/ui\'/',
            $src,
            '…and imports it through the barrel, the way the desks do, not by reaching into the module file.'
        );
        $this->assertStringNotContainsString('getStatusTone', $src,
            'The local helper is deleted, not left beside the shared one as a second answer.');
    }

    #[Test]
    public function the_shared_map_paints_both_return_states_as_a_hold(): void
    {
        $src = (string) file_get_contents(base_path('resources/js/Components/ui/statusLabel.js'));

        foreach (['returnedToEvaluation', 'returnedToAdmission'] as $status) {
            $this->assertMatchesRegularExpression(
                '/'.$status.':\s*\'warning\'/',
                $src,
                'A record parked by the return act must read as a hold wherever it is shown, including on paper.'
            );
        }
    }
}
