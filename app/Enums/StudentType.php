<?php

namespace App\Enums;

enum StudentType: string
{
    case FirstYear = 'firstYear';
    case Continuing = 'continuing';
    case Transferee = 'transferee';
    case Shifter = 'shifter';

    /**
     * Whether this type arrives with a broken continuity of study, which is what Irregular
     * records (§4.5). Ruled by the owner on 2026-10-06 (C-2): a student who changes program —
     * within their own department or into another — and a student who comes from another
     * school are both irregular, because the load they carry in was not earned as this
     * program's regular progression.
     *
     * A first-year and a returning regular student are not: their standing stays whatever the
     * evaluating department and the Registrar state, which is the design item 16 established.
     */
    public function arrivesIrregular(): bool
    {
        return $this === self::Transferee || $this === self::Shifter;
    }
}
