<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) 2015 ATM Consulting <support@atm-consulting.fr>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * 	\file		admin/setup_btp.php
 * 	\ingroup	btp
 * 	\brief		This file is an example module setup page
 * 				Put some comments here
 */
// Dolibarr environment
$res = @include("../../main.inc.php"); // From htdocs directory
if (! $res) {
    $res = @include("../../../main.inc.php"); // From "custom" directory
}

// Libraries
require_once DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php";
require_once '../lib/btp.lib.php';
dol_include_once('abricot/includes/lib/admin.lib.php');

// Translations
$langs->load("btp@btp");

// Access control
if (! $user->admin) {
    accessforbidden();
}

// Parameters
$action = GETPOST('action', 'alpha');

/*
 * Actions
 */
if (preg_match('/set_(.*)/',$action,$reg))
{
    $code=$reg[1];
    if (dolibarr_set_const($db, $code, GETPOST($code, 'none'), 'chaine', 0, '', $conf->entity) > 0)
    {
        header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    }
    else
    {
        dol_print_error($db);
    }
}

if (preg_match('/del_(.*)/',$action,$reg))
{
    $code=$reg[1];
    if (dolibarr_del_const($db, $code, 0) > 0)
    {
        Header("Location: ".$_SERVER["PHP_SELF"]);
        exit;
    }
    else
    {
        dol_print_error($db);
    }
}

/*
 * View
 */
$page_name = "BtpSetup";
llxHeader('', $langs->trans($page_name));

// Subheader
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">'
    . $langs->trans("BackToModuleList") . '</a>';
    print_fiche_titre($langs->trans($page_name), $linkback, 'tools');

    // Configuration header
    $head = btpAdminPrepareHead();
    dol_fiche_head(
        $head,
        'setup',
        $langs->trans("Module104911Name"),
        -1,
        "btp@btp"
        );



    print '<table class="noborder" width="100%">';

    setup_print_title($langs->trans("Parameters"));

    setup_print_on_off('PRODUCT_USE_UNITS'); // je ne sais pas a quoi sert cette conf j'ai fait une recherche, utilisé par workstation

    setup_print_on_off('BTP_SIMPLE_DISPLAY'); // je ne sais pas a quoi sert cette conf j'ai fait une recherche, utilisé par workstation

    if(floatval(DOL_VERSION) >= 8){
        setup_print_on_off('MAIN_ENABLE_IMPORT_LINKED_OBJECT_LINES', false, '', 'MAIN_ENABLE_IMPORT_LINKED_OBJECT_LINES_HELP');
    }


    if(floatval(DOL_VERSION) >= 10){
        setup_print_on_off('INVOICE_KEEP_DISCOUNT_LINES_AS_IN_ORIGIN', false, '', 'INVOICE_KEEP_DISCOUNT_LINES_AS_IN_ORIGIN_HELP');
    }


    // INVOICE_USE_SITUATION
    setup_print_title($langs->trans("SetupSituationTitle"));

    // FORCE reload page
    setup_print_on_off('INVOICE_USE_SITUATION', false, '', false, 300, true);

    if(getDolGlobalInt('INVOICE_USE_SITUATION')) {
        if(file_exists(DOL_DOCUMENT_ROOT . '/admin/facture_situation.php')
		|| file_exists(DOL_DOCUMENT_ROOT . '/admin/invoice_situation.php') // For X.x_btp compatible branch
        )
        {
		if(intval(DOL_VERSION) >= 20) $link = dol_buildpath('admin/invoice_situation.php',1);
		else $link =dol_buildpath('admin/facture_situation.php',1);
            print '<tr>';
            print '<td colspan="3">'.$langs->trans('SituationParamsAvailablesHere').' <a href="'.$link.'" >'.$langs->trans("SetupSituationTitle").'</a></td>'."\n";
            print '</tr>';
        }
    }
	elseif(intval(DOL_VERSION) >= 8){
		setup_print_on_off('INVOICE_USE_SITUATION_CREDIT_NOTE');
	}

	if(floatval(DOL_VERSION) >= 9){
		setup_print_on_off('INVOICE_USE_DEFAULT_DOCUMENT');
	}

	if(floatval(DOL_VERSION) >= 15.0){
		setup_print_on_off('BTP_USE_MARGINS_WITH_NOMENCLATURE_DETAILS');
	}

	if(floatval(DOL_VERSION) >= 13.0){
		setup_print_title($langs->trans("Project"));
		setup_print_on_off('PROJECT_SHOW_FORECAST_PROFIT_BOARD');
		setup_print_input_form_part('PROJECT_FORECAST_DEFAULT_THM');
	}


    print '</table>';

    llxFooter();

    $db->close();


