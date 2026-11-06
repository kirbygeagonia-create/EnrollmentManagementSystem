import { usePage } from '@inertiajs/react';

/**
 * The enrollment workflow's boxes, read from the one place that declares them.
 *
 * `WorkflowService::boxOrder()` is shared as `workflowPhases` — a LIST in signing order, one
 * entry per box with the office that owns it and the label a desk reads. Every phase number on
 * every surface is numbered out of that list, so the tracker, a desk page's header chip, the
 * desk launcher and the sidebar cannot each carry their own arithmetic for the same box.
 *
 * Before this module, the numbers were typed: the tracker drew the boxes from an object keyed
 * by office id (which JavaScript re-sorts ascending, so Registrar's box came first), and the
 * chrome spelled the same offices `Phase 0` … `Phase 8` from the old FDD module list. A
 * Registrar reading its own header saw "Phase 5" while the tracker under it said the
 * Registrar's box was the fourth of seven.
 */
export function useWorkflowBoxes() {
    const { workflowPhases = [] } = usePage().props;

    return Array.isArray(workflowPhases) ? workflowPhases : [];
}

/**
 * `Phase 4 of 7` for the named box, or null when the workflow declares no box by that label.
 *
 * Matched by the LABEL a desk reads rather than by office number or position: a position is
 * per-record (Assessment is absent for a continuing student) and an office id is a key nobody
 * at a window counts.
 */
export function workflowPhaseLabel(boxes, label) {
    if (!label) return null;

    const index = boxes.findIndex((box) => box.label === label);

    return index === -1 ? null : `Phase ${index + 1} of ${boxes.length}`;
}

/**
 * The chip a desk page paints: the phase, then the desk's own words beside it.
 *
 * A label the workflow does not declare yields null rather than a made-up number — a page for
 * a desk that holds no box (Admissions, the entrance exam lab, campus clearance) shows its
 * own words and says nothing about a phase, because inventing one is how the two vocabularies
 * came back.
 */
export function useDeskPhaseChip(label, words) {
    const phase = workflowPhaseLabel(useWorkflowBoxes(), label);

    if (phase === null) return words || null;

    return words ? `${phase} · ${words}` : phase;
}
