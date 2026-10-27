<?php

namespace App\Enums;

/**
 * Whether an examination result has been confirmed by the Registrar.
 *
 * §7.2 asks for the Registrar's final confirmation of an exam pass, and the owner ruled
 * 2026-10-26 that the recording office's entry stays provisional until that confirmation:
 * Guidance or the department scores the paper, and the Registrar makes it official. Two
 * values, not three — a rejected score is not a state this column can hold, because the
 * recording desk corrects its own row by re-recording it (which returns the new figure to
 * `provisional`) rather than having it refused.
 *
 * This is deliberately a separate column from `examResult`, which answers pass or fail and
 * is nullable: an unmarked paper is neither a passer nor a failure, and a row that has been
 * confirmed says nothing about which. Two questions, two columns — the same split §7.3's
 * crediting decision keeps from the grade it argues from.
 */
enum ExamStatus: string
{
    case Provisional = 'provisional';
    case Official = 'official';
}
