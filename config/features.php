<?php

/*
|--------------------------------------------------------------------------
| Module visibility
|--------------------------------------------------------------------------
|
| Modules that exist in the codebase but are switched off in the product.
| Routes stay live — this only controls whether the module is surfaced to
| users, in navigation and in the help centre.
|
| Set a flag to true to bring the module back.
|
*/

return [

    // The knowledge base has routes and views but no navigation entry, so the
    // only thing that ever pointed at it was the help centre. Documenting a
    // module nobody can reach from the UI sends people to a dead end.
    'knowledge_base' => env('FEATURE_KNOWLEDGE_BASE', false),

];
