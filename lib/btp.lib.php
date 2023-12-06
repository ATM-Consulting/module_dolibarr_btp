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
 *	\file		lib/btp.lib.php
 *	\ingroup	btp
 *	\brief		This file is an example module library
 *				Put some comments here
 */

function btpAdminPrepareHead()
{
    global $langs, $conf;

    $langs->load("btp@btp");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath("/btp/admin/setup_btp.php", 1);
    $head[$h][1] = $langs->trans("Setup");
    $head[$h][2] = 'setup';
    $h++;

    $head[$h][0] = dol_buildpath("/btp/admin/about_btp.php", 1);
    $head[$h][1] = $langs->trans("About");
    $head[$h][2] = 'about';
    $h++;

    // Show more tabs from modules
    // Entries must be declared in modules descriptor with line
    //$this->tabs = array(
    //	'entity:+tabname:Title:@metre:/metre/mypage.php?id=__ID__'
    //); // to add new tab
    //$this->tabs = array(
    //	'entity:-tabname:Title:@metre:/metre/mypage.php?id=__ID__'
    //); // to remove a tab
    complete_head_from_modules($conf, $langs, $object, $head, $h, 'btp');

    return $head;
}

function printForecastProfitBoard(Project &$object, &$listofreferent, $dates, $datee) {
	global $db, $langs, $user, $conf, $mysoc, $form, $hookmanager;

	$langs->load('btp@btp');

	$elementuser = new User($db);

	$balance_ht = 0;
	$balance_ttc = 0;

	print '<tr class="left forecast" style="display: none">';
	print '<th colspan="4" align="center">'.$langs->trans('ForecastProfit').'</th>';
	print '</tr>';
	print '<tr class="liste_titre forecast" style="display: none">';
	print '<td align="left" width="200">';
	$tooltiponprofit = $langs->trans("ProfitIsCalculatedWith")."<br>\n";
	$tooltiponprofitplus = $tooltiponprofitminus = '';
	foreach ($listofreferent as $key => $value) {
		$name = $langs->trans($value['name']);
		$qualified = $value['test'];
		$margin = $value['margin'];
		if ($qualified && isset($margin)) {		// If this element must be included into profit calculation ($margin is 'minus' or 'add')
			if ($margin == 'add') {
				$tooltiponprofitplus .= ' &gt; '.$name." (+)<br>\n";
			}
			if ($margin == 'minus') {
				$tooltiponprofitminus .= ' &gt; '.$name." (-)<br>\n";
			}
		}
	}
	$tooltiponprofit .= $tooltiponprofitplus;
	$tooltiponprofit .= $tooltiponprofitminus;
	print $form->textwithpicto($langs->trans("Element"), $tooltiponprofit);
	print '</td>';
	print '<td align="right" width="100">'.$langs->trans("Number").'</td>';
	print '<td align="right" width="100">'.$langs->trans("AmountHT").'</td>';
	print '<td align="right" width="100">'.$langs->trans("AmountTTC").'</td>';
	print '</tr>';

	foreach($listofreferent as $key => $value) {
		$name=$langs->trans($value['name']);
		$classname=$value['class'];
		$tablename=$value['table'];
		$datefieldname=$value['datefieldname'];
		$qualified=$value['test'];
		$margin = $value['margin'];
		if($qualified && isset($margin)) {
			$element = new $classname($db);

			$elementarray = $object->get_element_list($key, $tablename, $datefieldname, $dates, $datee, !empty($project_field)?$project_field:'fk_projet');
			if($key == 'project_task' && empty($object->lines)) {
				$object->getLinesArray($user);
			}

			if($key == 'project_task' && ! empty($object->lines)) {
				$total_ht_by_line = $total_ttc_by_line = 0;
				$thm = $conf->global->PROJECT_FORECAST_DEFAULT_THM;
				$i = count($object->lines);

				foreach($object->lines as $l) {
					$parameters = array('task' => $l);
					$resHook = $hookmanager->executeHooks('getForecastTHM', $parameters, $object, $action);
					if(! empty($resHook)) $thm = $resHook;
					$total_ht_by_line += price2num(($l->planned_workload / 3600) * $thm, 'MT');
				}
				$total_ttc_by_line += $total_ht_by_line;    // No TVA for tasks

				if ($margin != "add") {
					$total_ht_by_line *= -1;
					$total_ttc_by_line *= -1;
				}

				$balance_ht += $total_ht_by_line;
				$balance_ttc += $total_ttc_by_line;

				print '<tr class="oddeven forecast" style="display: none">';
				// Module
				print '<td align="left">'.$name.'</td>';
				// Nb
				print '<td align="right">'.$i.'</td>';
				// Amount HT
				print '<td align="right">';
				print price($total_ht_by_line);
				print '</td>';
				// Amount TTC
				print '<td align="right">';
				print price($total_ttc_by_line);
				print '</td>';
				print '</tr>';
			}
			else if (count($elementarray)>0 && is_array($elementarray))
			{
				$total_ht = 0;
				$total_ttc = 0;

				$num=count($elementarray);
				$TLinkedOrder = array();
				for ($i = 0; $i < $num; $i++)
				{
					$tmp=explode('_', $elementarray[$i]);
					$idofelement=$tmp[0];
					$idofelementuser=$tmp[1];

					$element->fetch($idofelement);
					if ($idofelementuser) $elementuser->fetch($idofelementuser);

					// Define if record must be used for total or not
					$qualifiedfortotal=true;
					if ($key == 'invoice')
					{
						if (! empty($element->close_code) && $element->close_code == 'replaced') $qualifiedfortotal=false;	// Replacement invoice, do not include into total
					}
					if ($key == 'propal')
					{
						if ($element->statut == Propal::STATUS_NOTSIGNED) $qualifiedfortotal=false;	// Refused proposal must not be included in total
						else {
							$element->fetchObjectLinked($element->id, 'propal', null, 'commande');
							if(! empty($element->linkedObjects['commande'])) {
								foreach($element->linkedObjects['commande'] as $linkedOrder) {
									if(! isset($TLinkedOrder['HT'])) $TLinkedOrder['HT'] = $linkedOrder->total_ht;
									else $TLinkedOrder['HT'] += $linkedOrder->total_ht;

									if(! isset($TLinkedOrder['TTC'])) $TLinkedOrder['TTC'] = $linkedOrder->total_ttc;
									else $TLinkedOrder['TTC'] += $linkedOrder->total_ttc;

									if(! isset($TLinkedOrder['nbOrder'])) $TLinkedOrder['nbOrder'] = 1;
									else $TLinkedOrder['nbOrder']++;
								}
							}
						}
					}

					if ($tablename != 'expensereport_det' && method_exists($element, 'fetch_thirdparty')) $element->fetch_thirdparty();

					// Define $total_ht_by_line
					if ($tablename == 'don' || $tablename == 'chargesociales' || $tablename == 'payment_various' || $tablename == 'payment_salary') $total_ht_by_line=$element->amount;
					elseif ($tablename == 'fichinter') $total_ht_by_line=$element->getAmount();
					elseif ($tablename == 'stock_mouvement') $total_ht_by_line=$element->price*abs($element->qty);
					elseif ($tablename == 'projet_task')
					{
						$thm = $conf->global->PROJECT_FORECAST_DEFAULT_THM;
						$total_ht_by_line = price2num(($element->planned_workload / 3600) * $thm, 'MT');
					}
					else $total_ht_by_line=$element->total_ht;

					// Define $total_ttc_by_line
					if ($tablename == 'don' || $tablename == 'chargesociales' || $tablename == 'payment_various' || $tablename == 'payment_salary') $total_ttc_by_line=$element->amount;
					elseif ($tablename == 'fichinter') $total_ttc_by_line=$element->getAmount();
					elseif ($tablename == 'stock_mouvement') $total_ttc_by_line=$element->price*abs($element->qty);
					elseif ($tablename == 'projet_task')
					{
						$defaultvat = get_default_tva($mysoc, $mysoc);
						$total_ttc_by_line = price2num($total_ht_by_line * (1 + ($defaultvat / 100)), 'MT');
					}
					else $total_ttc_by_line=$element->total_ttc;

					// Change sign of $total_ht_by_line and $total_ttc_by_line for some cases
					if ($tablename == 'payment_various')
					{
						if ($element->sens == 1)
						{
							$total_ht_by_line = -$total_ht_by_line;
							$total_ttc_by_line = -$total_ttc_by_line;
						}
					}

					// Add total if we have to
					if ($qualifiedfortotal)
					{
						$total_ht = $total_ht + $total_ht_by_line;
						$total_ttc = $total_ttc + $total_ttc_by_line;
					}
				}

				// Each element with at least one line is output
				$qualifiedforfinalprofit=true;
				if ($key == 'intervention' && !getDolGlobalString('PROJECT_INCLUDE_INTERVENTION_AMOUNT_IN_PROFIT')) $qualifiedforfinalprofit=false;
				//var_dump($key);

				// Calculate margin
				if ($qualifiedforfinalprofit)
				{
					if ($margin == 'add') {
						$total_revenue_ht += $total_ht;
					}

					if ($margin != "add")
					{
						$total_ht = -$total_ht;
						$total_ttc = -$total_ttc;
					}

					$balance_ht += $total_ht;
					$balance_ttc += $total_ttc;
				}

				print '<tr class="oddeven forecast" style="display: none">';
				// Module
				print '<td align="left">'.$name.'</td>';
				// Nb
				print '<td align="right">'.$i.'</td>';
				// Amount HT
				print '<td align="right">';
				if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
				else print price($total_ht);
				print '</td>';
				// Amount TTC
				print '<td align="right">';
				if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
				else print price($total_ttc);
				print '</td>';
				print '</tr>';

				if($key == 'propal' && ! empty($TLinkedOrder)) {
					$balance_ht -= $TLinkedOrder['HT'];
					$balance_ttc -= $TLinkedOrder['TTC'];

					print '<tr class="oddeven forecast" style="display: none">';
					// Module
					print '<td align="left">'.$langs->trans('OrdersFormProposals').'</td>';
					// Nb
					print '<td align="right">'.$TLinkedOrder['nbOrder'].'</td>';
					// Amount HT
					print '<td align="right">';
					if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
					else print '-'.price($TLinkedOrder['HT']);
					print '</td>';
					// Amount TTC
					print '<td align="right">';
					if (! $qualifiedforfinalprofit) print '<span class="opacitymedium">'.$form->textwithpicto($langs->trans("NA"), $langs->trans("AmountOfInteventionNotIncludedByDefault")).'</span>';
					else print '-'.price($TLinkedOrder['TTC']);
					print '</td>';
					print '</tr>';
				}
			}
		}
	}

	print '<tr class="liste_total forecast" style="display: none">';
	print '<td align="right" colspan="2" >'.$langs->trans("ForecastProfit").'</td>';
	print '<td align="right" >'.price(price2num($balance_ht, 'MT')).'</td>';
	print '<td align="right" >'.price(price2num($balance_ttc, 'MT')).'</td>';
	print '</tr>';

	if ($total_revenue_ht) {
		print '<tr class="liste_total forecast" style="display: none">';
		print '<td class="right" colspan="2">'.$langs->trans("Margin").'</td>';
		print '<td class="right">'.round(100 * $balance_ht / $total_revenue_ht, 1).'%</td>';
		print '<td class="right"></td>';
		print '</tr>';
	}

	print '<tr class="left forecast" style="display: none">';
	print '<th colspan="4" align="center">'.$langs->trans('RealProfit').'</th>';
	print '</tr>';
	?>
		<script>
			$(document).ready(function (){
				let benefTitle = $("table.table-fiche-title")[0];
				$(benefTitle).next().prepend($('.forecast'));
				$('.forecast').show();
			})
		</script>
	<?php

}
