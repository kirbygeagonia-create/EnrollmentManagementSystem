<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a desk tries to sign a workflow box that is already signed.
 *
 * Every office's affirmative act routes through WorkflowService::signStep, and until
 * now that method stamped whatever it was handed — so a second act on a box another
 * officer had already closed replaced the first signature's name and date in place and
 * emitted a second WorkflowStepSigned event for a box that only ever had one signing.
 * An office signature is the record the paper trail rests on, and a desk must not be
 * able to rewrite it by pressing a button twice, so the attempt is refused here and
 * bootstrap/app.php turns it into the card the officer sees.
 */
class WorkflowAlreadySignedException extends RuntimeException
{
    /**
     * @param  array<string, string>  $details  office, signedBy, signedDate, enrollment
     */
    public function __construct(public readonly array $details, string $message)
    {
        parent::__construct($message);
    }
}
