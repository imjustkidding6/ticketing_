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

    // The Jude assistant-hub connector (/connect-jude). Hidden at the product
    // owner's request. Unlike the knowledge base this ALSO closes its routes:
    // the page mints DesktopToken bearer credentials, so leaving it reachable by
    // direct URL while removing the only link to it would hide the entrance and
    // leave the door open. The connector's own api/assistant/* endpoints are
    // registered by the package and are not touched — an already-issued token
    // keeps working, which is why this is a visibility switch and not a kill
    // switch. Revoking live tokens is a deliberate, separate act.
    'jude_connector' => env('FEATURE_JUDE_CONNECTOR', false),

];
