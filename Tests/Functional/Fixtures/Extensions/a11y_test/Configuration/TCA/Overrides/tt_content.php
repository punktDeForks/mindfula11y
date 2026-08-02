<?php

declare(strict_types=1);

/*
 * Enables explicit CType allow-listing for the authorization test scenario.
 * Core ships tt_content.CType without authMode; integrators opt in exactly
 * like this, and PermissionService::checkRecordEditAccess() must honor it.
 */
defined('TYPO3') or die();

$GLOBALS['TCA']['tt_content']['columns']['CType']['config']['authMode'] = 'explicitAllow';

/*
 * An authMode column whose items only materialize at FormEngine render time
 * (itemsProcFunc): explicit_allowdeny grants can never be issued for such
 * values, so getAllowedAuthModeValues() must fail closed to the always-allowed
 * empty value instead of skipping the column. The proc func class is never
 * invoked by these tests — FormEngine is not rendered.
 */
$GLOBALS['TCA']['tt_content']['columns']['tx_a11ytest_dynamictype'] = [
    'label' => 'Dynamic type',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'authMode' => 'explicitAllow',
        'items' => [],
        'itemsProcFunc' => 'A11yTest\\NotInvoked\\ItemsProcFunc->items',
        'default' => '',
    ],
];

/*
 * A NULL-capable authMode column, modelling a legacy or third-party column whose
 * SQL definition permits NULL. checkAuthMode() casts the stored value to string
 * before its "blank is always allowed" short-circuit, so a NULL row is allowed
 * — but SQL NULL matches no IN () list, not even IN (''). The generated
 * predicate must therefore carry an explicit IS NULL branch, or such rows would
 * silently disappear from the listing for non-admins.
 *
 * The nullability comes from ext_tables.sql (`DEFAULT NULL`), NOT from a TCA
 * key: for a select column the Schema API hardcodes isNullable() to false and
 * derives nullability only from an items entry whose value is null, so a
 * `nullable => true` here would be inert and merely misleading. A hand-written
 * SQL definition wins over the generated one, which is exactly the third-party
 * situation being modelled.
 */
$GLOBALS['TCA']['tt_content']['columns']['tx_a11ytest_nullabletype'] = [
    'label' => 'Nullable type',
    'config' => [
        'type' => 'select',
        'renderType' => 'selectSingle',
        'authMode' => 'explicitAllow',
        'items' => [
            ['label' => 'Granted', 'value' => 'granted'],
            ['label' => 'Ungranted', 'value' => 'ungranted'],
        ],
    ],
];
