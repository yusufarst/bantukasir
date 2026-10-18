<?php

namespace Tests\Fixtures\StaticChecks;

// Negative sample of Pint (TEST_STRATEGY section 6): badly formatted on purpose.
function   badlyFormatted( int $a,int $b ): int {return $a+$b ;}
