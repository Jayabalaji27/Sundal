<?php

/*
 * Feature flags — set to false to disable a module from the sidebar.
 * Routes remain live (so existing bookmarks don't break) but nav entries disappear.
 * Flip to true to re-enable without a code revert.
 */
return [
    'portfolios'       => false,   // public project showcase — premature for v1
    'referral'         => false,   // referral program — enable when ready to grow
    'currency_mgmt'    => false,   // multi-currency management — India launch is single-currency
    'contract_types'   => false,   // moved to Settings → General dropdown
];
