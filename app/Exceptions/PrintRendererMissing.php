<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised before a paper is drawn, when the server has no browser to draw it with.
 *
 * Browsershot shells out to a real Chrome or Edge binary. When none is found the desk
 * used to land on the generic 500 page, which reads as the system having broken rather
 * than as one missing dependency the office head can supply — so the service says so
 * here and bootstrap/app.php turns it into the message the desk actually sees.
 */
class PrintRendererMissing extends RuntimeException {}
