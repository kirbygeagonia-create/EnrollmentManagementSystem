<?php
// The child half of lane227b: prints the two variables the residue probe needs to reach it.
echo 'DB_CONNECTION='.(getenv('DB_CONNECTION') === false ? 'unset' : getenv('DB_CONNECTION'))
    .' DB_DATABASE='.(getenv('DB_DATABASE') === false ? 'unset' : getenv('DB_DATABASE'))."\n";
