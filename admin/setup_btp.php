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
    if (dolibarr_set_const($db, $code, GETPOST($code), 'chaine', 0, '', $conf->entity) > 0)
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
    print_fiche_titre($langs->trans($page_name), $linkback);
    
    // Configuration header
    $head = btpAdminPrepareHead();
    dol_fiche_head(
        $head,
        'setup',
        $langs->trans("Module104911Name"),
        0,
        "btp@btp"
        );

    
    
    print '<table class="noborder" width="100%">';
    
    _print_title($langs->trans("Parameters"));
    
    _print_on_off('BTP_SIMPLE_DISPLAY');
    
    
    _print_title($langs->trans("SetupSituationTitle"));
    _print_on_off('INVOICE_USE_SITUATION');

    if(floatval(DOL_VERSION) >= 8){
        _print_on_off('INVOICE_USE_SITUATION_CREDIT_NOTE');
    }
    
    print '</table>';
    
    llxFooter();
    
    $db->close();
    
    
    
    
    function _print_title($title="")
    {
        global $langs;
        print '<tr class="liste_titre">';
        print '<td>'.$langs->trans($title).'</td>'."\n";
        print '<td align="center" width="20">&nbsp;</td>';
        print '<td align="center" ></td>'."\n";
        print '</tr>';
    }
    
    function _print_on_off($confkey, $title = false, $desc ='')
    {
        global $var, $bc, $langs, $conf;
        $var=!$var;
        
        print '<tr '.$bc[$var].'>';
        print '<td>'.($title?$title:$langs->trans($confkey));
        if(!empty($desc))
        {
            print '<br><small>'.$langs->trans($desc).'</small>';
        }
        print '</td>';
        print '<td align="center" width="20">&nbsp;</td>';
        print '<td align="center" width="300">';
        print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
        print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
        print '<input type="hidden" name="action" value="set_'.$confkey.'">';
        print ajax_constantonoff($confkey);
        print '</form>';
        print '</td></tr>';
    }