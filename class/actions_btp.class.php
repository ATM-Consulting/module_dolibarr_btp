<?php
/* <one line to give the program's name and a brief idea of what it does.>
 * Copyright (C) <year>  <name of author>
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
 * \file    htdocs/modulebuilder/template/class/actions_mymodule.class.php
 * \ingroup mymodule
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsMyModule
 */
require_once __DIR__ . '/../backport/v19/core/class/commonhookactions.class.php';
class ActionsBtp extends btp\RetroCompatCommonHookActions
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;
    /**
     * @var string Error
     */
    public $error = '';
    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $this->results for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;

	/**
	 * @var array list of elements linked to a project
	 * used for projet/element.php customisation
	 */
	public $listofreferent;

	public $forecastProfitedPrinted = false;


    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        $contexts = explode(':',$parameters['context']);

        if (in_array('invoicecard',$contexts)) {

        }

    }

    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2'))) {  // do something only for the context 'somecontext1' or 'somecontext2'

            foreach($parameters['toselect'] as $objectid)
            {
                // Do action on each object id

            }
        }

    }


    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array()         $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2')))  // do something only for the context 'somecontext'
        {
            $this->resprints = '<option value="0"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MyModuleMassAction").'</option>';
        }

    }

	public function completeListOfReferent($parameters, &$object, &$action, $hookmanager)
	{
		global $conf;

		if (getDolGlobalInt('PROJECT_SHOW_FORECAST_PROFIT_BOARD')) $this->listofreferent = $parameters['listofreferent'];
	}

	public function printOverviewProfit($parameters, &$object, &$action, $hookmanager)
	{
		global $conf, $user, $langs;

//		print 'lol';
		dol_include_once('btp/lib/btp.lib.php');

		if (getDolGlobalInt('PROJECT_SHOW_FORECAST_PROFIT_BOARD') && ! $this->forecastProfitedPrinted)
		{
			$this->listofreferent['propal']['margin'] = 'add';
			$this->listofreferent['propal']['name'] = 'ProposalsExcludingRefused';
			$this->listofreferent['order']['margin'] = 'add';
			$this->listofreferent['order_supplier']['margin'] = 'minus';
			unset($this->listofreferent['invoice']['margin'], $this->listofreferent['invoice_supplier']['margin']);

			printForecastProfitBoard($object, $this->listofreferent, $parameters['dates'], $parameters['datee']);
			$this->forecastProfitedPrinted = true;
		}

		return 0;
	}


}
