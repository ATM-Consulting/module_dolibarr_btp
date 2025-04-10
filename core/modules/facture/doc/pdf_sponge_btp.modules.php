<?php
/* Copyright (C) 2004-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2005-2012	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2008		Raphael Bertrand		<raphael.bertrand@resultic.fr>
 * Copyright (C) 2010-2014	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2012		Christophe Battarel	<christophe.battarel@altairis.fr>
 * Copyright (C) 2012		Cédric Salvador		<csalvador@gpcsolutions.fr>
 * Copyright (C) 2012-2014	Raphaël Doursenaud	<rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2015		Marcos García		<marcosgdf@gmail.com>
 * Copyright (C) 2017		Ferran Marcet		<fmarcet@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/core/modules/facture/doc/pdf_sponge.modules.php
 *	\ingroup    facture
 *	\brief      File of class to generate customers invoices from sponge model
 */

require_once DOL_DOCUMENT_ROOT.'/core/modules/facture/modules_facture.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php';
// TSubtotal
if (isModEnabled('subtotal')) dol_include_once('/subtotal/class/subtotal.class.php');


/**
 *	Class to manage PDF invoice template sponge
 */
class pdf_sponge_btp extends ModelePDFFactures
{
	/**
	 * @var DoliDb Database handler
	 */
	public $db;

	/**
	 * @var string model name
	 */
	public $name;

	/**
	 * @var string model description (short text)
	 */
	public $description;

	/**
	 * @var int 	Save the name of generated file as the main doc when generating a doc with this template
	 */
	public $update_main_doc_field;

	/**
	 * @var string document type
	 */
	public $type;

	/**
	 * @var array() Minimum version of PHP required by module.
	 * e.g.: PHP ≥ 5.3 = array(5, 3)
	 */
	public $phpmin = array(5, 2);

	/**
	 * Dolibarr version of the loaded document
	 * @public string
	 */
	public $version = 'dolibarr';

	public $page_largeur;
	public $page_hauteur;
	public $format;
	public $marge_gauche;
	public $marge_droite;
	public $marge_haute;
	public $marge_basse;

	public $emetteur;	// Objet societe qui emet

	/**
	 * @var bool Situation invoice type
	 */
	public $situationinvoice;

	/**
	 * @var float X position for the situation progress column
	 */
	public $posxprogress;


	/**
	 *	Constructor
	 *
	 *  @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		global $conf,$langs,$mysoc,$object;

		// for retro compatibility
		if(getDolGlobalInt('INVOICE_USE_SITUATION_RETAINED_WARRANTY') && !getDolGlobalInt('INVOICE_USE_RETAINED_WARRANTY')) {
			// before it was only for final situation invoice
			$conf->global->INVOICE_USE_RETAINED_WARRANTY = getDolGlobalInt('INVOICE_USE_SITUATION_RETAINED_WARRANTY');
			$conf->global->USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL = 1;
		}

		// Translations
		$langs->loadLangs(array("main", "bills"));
		$langs->load("btp@btp");

		$this->db = $db;
		$this->name = "sponge_btp";
		$this->description = $langs->trans('PDFSpongeDescription');
		$this->update_main_doc_field = 1;		// Save the name of generated file as the main doc when generating a doc with this template

		// Dimensiont page
		$this->type = 'pdf';
		$formatarray=pdf_getFormat();

		$this->page_largeur = 297;
		$this->page_hauteur = 210;
        $this->posx_new_cumul = 94;
        $this->posx_cumul_anterieur = 130;
        $this->posx_month = 166;

		$this->format = array($this->page_largeur,$this->page_hauteur);
		$this->marge_gauche = getDolGlobalInt('MAIN_PDF_MARGIN_LEFT', 10);
		$this->marge_droite = getDolGlobalInt('MAIN_PDF_MARGIN_RIGHT', 10);
		$this->marge_haute  = getDolGlobalInt('MAIN_PDF_MARGIN_TOP',10);
		$this->marge_basse  = getDolGlobalInt('MAIN_PDF_MARGIN_BOTTOM',10);

		$this->option_logo = 1;                    // Affiche logo
		$this->option_tva = 1;                     // Gere option tva FACTURE_TVAOPTION
		$this->option_modereg = 1;                 // Affiche mode reglement
		$this->option_condreg = 1;                 // Affiche conditions reglement
		$this->option_codeproduitservice = 1;      // Affiche code produit-service
		$this->option_multilang = 1;               // Dispo en plusieurs langues
		$this->option_escompte = 1;                // Affiche si il y a eu escompte
		$this->option_credit_note = 1;             // Support credit notes
		$this->option_freetext = 1;				   // Support add of a personalised text
		$this->option_draft_watermark = 1;		   // Support add of a watermark on drafts

		$this->franchise=!$mysoc->tva_assuj;

		// Get source company
		$this->emetteur=$mysoc;
		if (empty($this->emetteur->country_code)) $this->emetteur->country_code=substr($langs->defaultlang,-2);    // By default, if was not defined

		// Define position of columns
		$this->posxdesc=$this->marge_gauche+1; // used for notes ans other stuff

		//  Use new system for position of columns, view  $this->defineColumnField()

		$this->tva=array();
		$this->localtax1=array();
		$this->localtax2=array();
		$this->atleastoneratenotnull=0;
		$this->atleastonediscount=0;
		$this->situationinvoice=false;


		if (!empty($object)) $this->TDataSituation = $this->_getDataSituation($object);
	}


	/**
	 *  Function to build pdf onto disk
	 *
	 *  @param		Object		$object				Object to generate
	 *  @param		Translate	$outputlangs		Lang output object
	 *  @param		string		$srctemplatepath	Full path of source filename for generator using a template file
	 *  @param		int			$hidedetails		Do not show line details
	 *  @param		int			$hidedesc			Do not show desc
	 *  @param		int			$hideref			Do not show ref
	 *  @return     int         	    			1=OK, 0=KO
	 */
	function write_file($object,$outputlangs,$srctemplatepath='',$hidedetails=0,$hidedesc=0,$hideref=0)
	{
		global $user,$langs,$conf,$mysoc,$db,$hookmanager,$nblignes;

		if (! is_object($outputlangs)) $outputlangs=$langs;
		// For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
		if (getDolGlobalInt('MAIN_USE_FPDF')) $outputlangs->charset_output='ISO-8859-1';

		if (empty($object) || ( $object->type != Facture::TYPE_SITUATION && ($object->type != Facture::TYPE_CREDIT_NOTE &&  !empty($object->situation_cycle_ref))))
		{
			setEventMessage($langs->trans('BtpWarningsObjectIsNotASituation'), 'warnings');
			return 1;
		}

		// Translations
		$outputlangs->loadLangs(array("main", "bills", "products", "dict", "companies"));

		$nblignes = count($object->lines);

		// Loop on each lines to detect if there is at least one image to show
		$realpatharray=array();
		$this->atleastonephoto = false;
		if (getDolGlobalInt('MAIN_GENERATE_INVOICES_WITH_PICTURE'))
		{
			$objphoto = new Product($this->db);

			for ($i = 0 ; $i < $nblignes ; $i++)
			{
				if (empty($object->lines[$i]->fk_product)) continue;

				$objphoto->fetch($object->lines[$i]->fk_product);
				//var_dump($objphoto->ref);exit;
				if (getDolGlobalInt('PRODUCT_USE_OLD_PATH_FOR_PHOTO'))
				{
					$pdir[0] = get_exdir($objphoto->id,2,0,0,$objphoto,'product') . $objphoto->id ."/photos/";
					$pdir[1] = get_exdir(0,0,0,0,$objphoto,'product') . dol_sanitizeFileName($objphoto->ref).'/';
				}
				else
				{
					$pdir[0] = get_exdir(0,0,0,0,$objphoto,'product') . dol_sanitizeFileName($objphoto->ref).'/';				// default
					$pdir[1] = get_exdir($objphoto->id,2,0,0,$objphoto,'product') . $objphoto->id ."/photos/";	// alternative
				}

				$arephoto = false;
				foreach ($pdir as $midir)
				{
					if (! $arephoto)
					{
						$dir = $conf->product->dir_output.'/'.$midir;

						foreach ($objphoto->liste_photos($dir,1) as $key => $obj)
						{
							if (!getDolGlobalInt('CAT_HIGH_QUALITY_IMAGES'))		// If CAT_HIGH_QUALITY_IMAGES not defined, we use thumb if defined and then original photo
							{
								if ($obj['photo_vignette'])
								{
									$filename= $obj['photo_vignette'];
								}
								else
								{
									$filename=$obj['photo'];
								}
							}
							else
							{
								$filename=$obj['photo'];
							}

							$realpath = $dir.$filename;
							$arephoto = true;
							$this->atleastonephoto = true;
						}
					}
				}

				if ($realpath && $arephoto) $realpatharray[$i]=$realpath;
			}
		}

		//if (count($realpatharray) == 0) $this->posxpicture=$this->posxtva;

		if ($conf->facture->dir_output)
		{
			$object->fetch_thirdparty();

			$deja_regle = $object->getSommePaiement((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_credit_notes_included = $object->getSumCreditNotesUsed((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? 1 : 0);
			$amount_deposits_included = $object->getSumDepositsUsed((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? 1 : 0);

			// Definition of $dir and $file
			if ($object->specimen)
			{
				$dir = $conf->facture->dir_output;
				$file = $dir . "/SPECIMEN.pdf";
			}
			else
			{
				$objectref = dol_sanitizeFileName($object->ref);
				$dir = $conf->facture->dir_output . "/" . $objectref;
				$file = $dir . "/" . $objectref . ".pdf";
			}
			if (! file_exists($dir))
			{
				if (dol_mkdir($dir) < 0)
				{
					$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
					return 0;
				}
			}

			if (file_exists($dir))
			{
				// Add pdfgeneration hook
				if (! is_object($hookmanager))
				{
					include_once DOL_DOCUMENT_ROOT.'/core/class/hookmanager.class.php';
					$hookmanager=new HookManager($this->db);
				}
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('beforePDFCreation',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks

				// Set nblignes with the new facture lines content after hook
				$nblignes = count($object->lines);
				$nbpayments = count($object->getListOfPayments());
		        $nbprevsituation = !empty($object->tab_previous_situation_invoice) &&is_array($object->tab_previous_situation_invoice) ? count($object->tab_previous_situation_invoice) : 0;

				// Create pdf instance
				$pdf=pdf_getInstance($this->format);
				$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
				$pdf->SetAutoPageBreak(1,0);

	            $heightforinfotot = 30;	// Height reserved to output the info and total part and payment part
		        if(!getDolGlobalInt('INVOICE_NO_PAYMENT_DETAILS') && $nbpayments > 0) $heightforinfotot += 4 * ($nbpayments + 3);
				if($nbprevsituation > 0) $heightforinfotot += 4 * ($nbprevsituation + 3);
				$heightforfreetext= getDolGlobalInt('MAIN_PDF_FREETEXT_HEIGHT',5);	// Height reserved to output the free text on last page
				$heightforfooter = $this->marge_basse + ((getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS')) ? 22 : 12);	// Height reserved to output the footer (value include bottom margin)

				if (class_exists('TCPDF'))
				{
					$pdf->setPrintHeader(false);
					$pdf->setPrintFooter(false);
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs));

				// Set path to the background PDF File
				if (!empty(getDolGlobalString('MAIN_ADD_PDF_BACKGROUND')))
				{
					$pagecount = $pdf->setSourceFile($conf->mycompany->dir_output.'/' . getDolGlobalString('MAIN_ADD_PDF_BACKGROUND'));
					$tplidx = $pdf->importPage(1);
				}

				$pdf->Open();
				$pagenb=0;
				$pdf->SetDrawColor(128,128,128);

				$pdf->SetTitle($outputlangs->convToOutputCharset($object->ref));
				$pdf->SetSubject($outputlangs->transnoentities("PdfInvoiceTitle"));
				$pdf->SetCreator("Dolibarr ".DOL_VERSION);
				$pdf->SetAuthor($outputlangs->convToOutputCharset($user->getFullName($outputlangs)));
				$pdf->SetKeyWords($outputlangs->convToOutputCharset($object->ref)." ".$outputlangs->transnoentities("PdfInvoiceTitle")." ".$outputlangs->convToOutputCharset($object->thirdparty->name));
				if (getDolGlobalInt('MAIN_DISABLE_PDF_COMPRESSION')) $pdf->SetCompression(false);

				$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right

				// Does we have at least one line with discount $this->atleastonediscount
				foreach ($object->lines as $line) {
					if ($line->remise_percent){
						$this->atleastonediscount = true;
						break;
					}
				}


				// Situation invoice handling
				if ($object->situation_cycle_ref)
				{
					$this->situationinvoice = true;
				}


				$this->page_largeur = 210;
				$this->page_hauteur = 297;

				// New page
				$pdf->AddPage();
				if (! empty($tplidx)) $pdf->useTemplate($tplidx);
				$pagenb++;

				$top_shift = $this->_pagehead($pdf, $object, 1, $outputlangs);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0,0,0);
				/*
				$tab_top = 90+$top_shift;
				$tab_top_newpage = (empty(getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD'))?42+$top_shift:10);
				$tab_height = 130-$top_shift;
				$tab_height_newpage = 150;
				if (empty($conf->global->MAIN_PDF_DONOTREPEAT_HEAD)) $tab_height_newpage -= $top_shift;*/



				/**** DEBUT TABLEAU SPECIFIQUE ****/

				$tab_top = 90;
				$tab_top_newpage = (getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 10 : 42);
				$tab_height = 130;
				$tab_height_newpage = 150;

				$this->_tableauBtp($pdf, $tab_top, $this->page_hauteur - 100 - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
				$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;

				$this->_pagefoot($pdf,$object,$outputlangs,1);

				$pdf->AddPage();
				$pdf->setPage(2);
				$pagenb++;

				$this->page_largeur = 297;
				$this->page_hauteur = 210;

				$pdf->setPageOrientation('L', 1, $heightforfooter+$heightforfreetext+$heightforinfotot);

				/**** FIN TABLEAU SPECIFIQUE ****/

				$this->_pagehead($pdf, $object, 0, $outputlangs, FALSE);
				$pdf->SetFont('','', $default_font_size - 1);
				$pdf->MultiCell(0, 3, '');		// Set interline to 3
				$pdf->SetTextColor(0,0,0);

				$tab_top = 42;
				$tab_top_newpage = (getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 10 : 42);
				$tab_height = 130;
				$tab_height_newpage = 150;





				// Incoterm
				$height_incoterms = 0;
				if (isModEnabled('incoterm'))
				{
					$desc_incoterms = $object->getIncotermsForPDF();
					if ($desc_incoterms)
					{
						$tab_top -= 2;

						$pdf->SetFont('','', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top-1, dol_htmlentitiesbr($desc_incoterms), 0, 1);
						$nexY = max($pdf->GetY(),$nexY);
						$height_incoterms=$nexY-$tab_top;

						// Rect prend une longueur en 3eme param
						$pdf->SetDrawColor(192,192,192);
						$pdf->Rect($this->marge_gauche, $tab_top-1, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $height_incoterms+1);

						$tab_top = $nexY+6;
						$height_incoterms += 4;
					}
				}

				// Affiche notes
				$notetoshow=empty($object->note_public)?'':$object->note_public;
				if (getDolGlobalInt('MAIN_ADD_SALE_REP_SIGNATURE_IN_NOTE'))
				{
					// Get first sale rep
					if (is_object($object->thirdparty))
					{
						$salereparray=$object->thirdparty->getSalesRepresentatives($user);
						$salerepobj=new User($this->db);
						$salerepobj->fetch($salereparray[0]['id']);
						if (! empty($salerepobj->signature)) $notetoshow=dol_concatdesc($notetoshow, $salerepobj->signature);
					}
				}

				$pagenb = $pdf->getPage();
				if ($notetoshow)
				{
					$tab_top -= 2;

					$tab_width = $this->page_largeur-$this->marge_gauche-$this->marge_droite;
					$pageposbeforenote = $pagenb;

					$substitutionarray=pdf_getSubstitutionArray($outputlangs, null, $object);
					complete_substitutions_array($substitutionarray, $outputlangs, $object);
					$notetoshow = make_substitutions($notetoshow, $substitutionarray, $outputlangs);


					$pdf->startTransaction();

					$pdf->SetFont('','', $default_font_size - 1);
					$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
					// Description
					$pageposafternote=$pdf->getPage();
					$posyafter = $pdf->GetY();

					if($pageposafternote>$pageposbeforenote )
					{
						$pdf->rollbackTransaction(true);

						// prepar pages to receive notes
						while ($pagenb < $pageposafternote) {
							$pdf->AddPage();
							$pagenb++;
							if (! empty($tplidx)) $pdf->useTemplate($tplidx);
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
							// $this->_pagefoot($pdf,$object,$outputlangs,1);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						}

						// back to start
						$pdf->setPage($pageposbeforenote);
						$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
						$pdf->SetFont('','', $default_font_size - 1);
						$pdf->writeHTMLCell(190, 3, $this->posxdesc-1, $tab_top, dol_htmlentitiesbr($notetoshow), 0, 1);
						$pageposafternote=$pdf->getPage();

						$posyafter = $pdf->GetY();

						if ($posyafter > ($this->page_hauteur - ($heightforfooter+$heightforfreetext+20)))	// There is no space left for total+free text
						{
							$pdf->AddPage('','',true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							$pdf->setTopMargin($tab_top_newpage);
							// The only function to edit the bottom margin of current page to set it.
							$pdf->setPageOrientation('', 1, $heightforfooter + $heightforfreetext);
							//$posyafter = $tab_top_newpage;
						}


						// apply note frame to previus pages
						$i = $pageposbeforenote;
						while ($i < $pageposafternote) {
							$pdf->setPage($i);


							$pdf->SetDrawColor(128,128,128);
							// Draw note frame
							if($i>$pageposbeforenote){
								$height_note = $this->page_hauteur - ($tab_top_newpage + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top_newpage-1, $tab_width, $height_note + 1);
							}
							else{
								$height_note = $this->page_hauteur - ($tab_top + $heightforfooter);
								$pdf->Rect($this->marge_gauche, $tab_top-1, $tab_width, $height_note + 1);
							}

							// Add footer
							$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.
							$this->_pagefoot($pdf,$object,$outputlangs,1);

							$i++;
						}

						// apply note frame to last page
						$pdf->setPage($pageposafternote);
						if (! empty($tplidx)) $pdf->useTemplate($tplidx);
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
						$height_note=$posyafter-$tab_top_newpage;
						$pdf->Rect($this->marge_gauche, $tab_top_newpage-1, $tab_width, $height_note+1);

					}
					else // No pagebreak
					{
						$pdf->commitTransaction();
						$posyafter = $pdf->GetY();
						$height_note=$posyafter-$tab_top;
						$pdf->Rect($this->marge_gauche, $tab_top-1, $tab_width, $height_note+1);


						if($posyafter > ($this->page_hauteur - ($heightforfooter+$heightforfreetext+20)) )
						{
							// not enough space, need to add page
							$pdf->AddPage('','',true);
							$pagenb++;
							$pageposafternote++;
							$pdf->setPage($pageposafternote);
							if (! empty($tplidx)) $pdf->useTemplate($tplidx);
							if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);

							$posyafter = $tab_top_newpage;
						}

					}

					$tab_height = $tab_height - $height_note;
					$tab_top = $posyafter +6;
				}
				else
				{
					$height_note=0;
				}

				$iniY = $tab_top + 7;
				$curY = $tab_top + 7;
				$nexY = $tab_top + 7;

				// Use new auto collum system
				$this->prepareArrayColumnField($object,$outputlangs,$hidedetails,$hidedesc,$hideref);

				// Loop on each lines
				$pageposbeforeprintlines=$pdf->getPage();
				$pagenb = $pageposbeforeprintlines;
				for ($i = 0; $i < $nblignes; $i++)
				{
                    $curY = $nexY;
                    $pdf->SetFont('','', $default_font_size - 1);   // Into loop to work with multipage
                    $pdf->SetTextColor(0,0,0);

                    // Define size of image if we need it
                    $imglinesize=array();
                    if (! empty($realpatharray[$i])) $imglinesize=pdf_getSizeForImage($realpatharray[$i]);

                    $pdf->setTopMargin($tab_top_newpage);
                    $pdf->setPageOrientation('', 1, $heightforfooter+$heightforfreetext+$heightforinfotot);	// The only function to edit the bottom margin of current page to set it.
                    $pageposbefore=$pdf->getPage();

                    $showpricebeforepagebreak=1;
                    $posYAfterImage=0;
                    $posYAfterDescription=0;

                    if($this->getColumnStatus('photo'))
                    {
                        // We start with Photo of product line
                        if (isset($imglinesize['width']) && isset($imglinesize['height']) && ($curY + $imglinesize['height']) > ($this->page_hauteur-($heightforfooter+$heightforfreetext+$heightforinfotot)))	// If photo too high, we moved completely on new page
                        {
                            $pdf->AddPage('','',true);
                            if (! empty($tplidx)) $pdf->useTemplate($tplidx);
                            $pdf->setPage($pageposbefore+1);

                            $curY = $tab_top_newpage;
                            $showpricebeforepagebreak=0;
                        }

                        if (!empty($this->cols['photo']) && isset($imglinesize['width']) && isset($imglinesize['height']))
                        {
                            $pdf->Image($realpatharray[$i], $this->getColumnContentXStart('photo'), $curY, $imglinesize['width'], $imglinesize['height'], '', '', '', 2, 300);	// Use 300 dpi
                            // $pdf->Image does not increase value return by getY, so we save it manually
                            $posYAfterImage=$curY+$imglinesize['height'];
                        }
                    }

                    // Description of product line
                    if ($this->getColumnStatus('desc'))
                    {
                        $pdf->startTransaction();

                        pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->getColumnContentWidth('desc'),3,$this->getColumnContentXStart('desc'),$curY,$hideref,$hidedesc);

                        $pageposafter=$pdf->getPage();
                        if ($pageposafter > $pageposbefore)	// There is a pagebreak
                        {
                            $pdf->rollbackTransaction(true);
                            $pageposafter=$pageposbefore;
                            //print $pageposafter.'-'.$pageposbefore;exit;
                            $pdf->setPageOrientation('', 1, $heightforfooter);	// The only function to edit the bottom margin of current page to set it.
                            pdf_writelinedesc($pdf,$object,$i,$outputlangs,$this->getColumnContentWidth('desc'),3,$this->getColumnContentXStart('desc'),$curY,$hideref,$hidedesc);
                            $pageposafter=$pdf->getPage();
                            $posyafter=$pdf->GetY();
                            //var_dump($posyafter); var_dump(($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot))); exit;
                            if ($i == ($nblignes-1) && $posyafter > ($this->page_hauteur - ($heightforfooter+$heightforfreetext+$heightforinfotot)))
                            {
                                $pdf->AddPage('','',true);
                                if (! empty($tplidx)) $pdf->useTemplate($tplidx);
                                $pdf->setPage($pageposafter+1);
                            }
                            else
                            {
                                // We found a page break
                                $showpricebeforepagebreak=0;
                            }
                        }
                        else	// No pagebreak
                        {
                            $pdf->commitTransaction();
                        }
                        $posYAfterDescription=$pdf->GetY();
                    }

                    $nexY = $pdf->GetY();
                    $pageposafter=$pdf->getPage();
                    $pdf->setPage($pageposbefore);
                    $pdf->setTopMargin($this->marge_haute);
                    $pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

                    // We suppose that a too long description or photo were moved completely on next page
                    if ($pageposafter > $pageposbefore && empty($showpricebeforepagebreak)) {
                        $pdf->setPage($pageposafter); $curY = $tab_top_newpage;
                    }

                    $pdf->SetFont('','', $default_font_size - 1);   // On repositionne la police par defaut

                    // VAT Rate
                    if ($this->getColumnStatus('vat'))
                    {
                        $vat_rate = pdf_getlinevatrate($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'vat', $vat_rate);
                        $nexY = max($pdf->GetY(),$nexY);
                    }

                    // Unit price before discount
                    if ($this->getColumnStatus('subprice'))
                    {
                        $up_excl_tax = pdf_getlineupexcltax($object, $i, $outputlangs, $hidedetails);
                        $this->printStdColumnContent($pdf, $curY, 'subprice', $up_excl_tax);
                        $nexY = max($pdf->GetY(),$nexY);
                    }

                    // Quantity
					// Enough for 6 chars
					if ($this->getColumnStatus('qty'))
					{
						$qty = pdf_getlineqty($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'qty', $qty);
						$nexY = max($pdf->GetY(),$nexY);
					}

					// Situation progress
					if ($this->getColumnStatus('progress'))
					{
						$progress = pdf_getlineprogress($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'progress', $progress);
						$nexY = max($pdf->GetY(),$nexY);
					}

					// Unit
					if ($this->getColumnStatus('unit'))
					{
						$unit = pdf_getlineunit($object, $i, $outputlangs, $hidedetails, $hookmanager);
						$this->printStdColumnContent($pdf, $curY, 'unit', $unit);
						$nexY = max($pdf->GetY(),$nexY);
					}

					// Discount on line
					if ($this->getColumnStatus('discount') && $object->lines[$i]->remise_percent)
					{
						$remise_percent = pdf_getlineremisepercent($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'discount', $remise_percent);
						$nexY = max($pdf->GetY(),$nexY);
					}

					// Total HT line
					if ($this->getColumnStatus('totalexcltax'))
					{
						$total_excl_tax = pdf_getlinetotalexcltax($object, $i, $outputlangs, $hidedetails);
						$this->printStdColumnContent($pdf, $curY, 'totalexcltax', $total_excl_tax);
						$nexY = max($pdf->GetY(),$nexY);
					}



					// Récupération des infos de la ligne précédente
					$TInfosLigneSituationPrecedente = $this->_getInfosLineDerniereSituation($object, $object->lines[$i]);

					// "Sommes"
					if(!class_exists('TSubtotal') || !TSubtotal::isModSubtotalLine($object->lines[$i])){

						$columkey = 'btpsomme';
						if ($this->getColumnStatus($columkey))
						{
							$printval = price($TInfosLigneSituationPrecedente['total_ht_without_progress'] ?? 0);
							$this->printStdColumnContent($pdf, $curY, $columkey, $printval);
							$nexY = max($pdf->GetY(),$nexY);
						}


						// "Progession actuelle mois"
						$columkey = 'progress_amount';
						if ($this->getColumnStatus($columkey))
						{
							$printval = price($object->lines[$i]->total_ht);
							$this->printStdColumnContent($pdf, $curY, $columkey, $printval);
							$nexY = max($pdf->GetY(),$nexY);
						}

						// "Progession précédente line"
						$columkey = 'prev_progress';
						if ($this->getColumnStatus($columkey))
						{
							$printval = ($TInfosLigneSituationPrecedente['progress_prec']??0).'%';
							$this->printStdColumnContent($pdf, $curY, $columkey, $printval);
							$nexY = max($pdf->GetY(),$nexY);
						}

						// "Progession précédente mois"
						$columkey = 'prev_progress_amount';
						if ($this->getColumnStatus($columkey))
						{
							$printval = price($TInfosLigneSituationPrecedente['total_ht']??0);
							$this->printStdColumnContent($pdf, $curY, $columkey, $printval);
							$nexY = max($pdf->GetY(),$nexY);
						}


					}




					$parameters=array(
						'object' => $object,
						'i' => $i,
						'pdf' =>& $pdf,
						'curY' =>& $curY,
						'nexY' =>& $nexY,
						'outputlangs' => $outputlangs,
						'hidedetails' => $hidedetails
					);
					$reshook=$hookmanager->executeHooks('printPDFline',$parameters,$this);    // Note that $object may have been modified by hook



					$sign=1;
					if (isset($object->type) && $object->type == 2 && getDolGlobalInt('INVOICE_POSITIVE_CREDIT_NOTE')) $sign=-1;
					// Collecte des totaux par valeur de tva dans $this->tva["taux"]=total_tva
					$prev_progress = $object->lines[$i]->get_prev_progress($object->id, true);
					if ($prev_progress > 0 && !empty($object->lines[$i]->situation_percent)) // Compute progress from previous situation
					{
						if (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) $tvaligne = $sign * $object->lines[$i]->multicurrency_total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
						else $tvaligne = $sign * $object->lines[$i]->total_tva * ($object->lines[$i]->situation_percent - $prev_progress) / $object->lines[$i]->situation_percent;
					} else {
						if (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) $tvaligne= $sign * $object->lines[$i]->multicurrency_total_tva;
						else $tvaligne= $sign * $object->lines[$i]->total_tva;
					}

					$localtax1ligne=$object->lines[$i]->total_localtax1;
					$localtax2ligne=$object->lines[$i]->total_localtax2;
					$localtax1_rate=$object->lines[$i]->localtax1_tx;
					$localtax2_rate=$object->lines[$i]->localtax2_tx;
					$localtax1_type=$object->lines[$i]->localtax1_type;
					$localtax2_type=$object->lines[$i]->localtax2_type;

					if ($object->remise_percent) $tvaligne-=($tvaligne*$object->remise_percent)/100;
					if ($object->remise_percent) $localtax1ligne-=($localtax1ligne*$object->remise_percent)/100;
					if ($object->remise_percent) $localtax2ligne-=($localtax2ligne*$object->remise_percent)/100;

					$vatrate=(string) $object->lines[$i]->tva_tx;

					// Retrieve type from database for backward compatibility with old records
					if ((! isset($localtax1_type) || $localtax1_type=='' || ! isset($localtax2_type) || $localtax2_type=='') // if tax type not defined
						&& (! empty($localtax1_rate) || ! empty($localtax2_rate))) // and there is local tax
					{
						$localtaxtmp_array=getLocalTaxesFromRate($vatrate,0, $object->thirdparty, $mysoc);
						$localtax1_type = $localtaxtmp_array[0];
						$localtax2_type = $localtaxtmp_array[2];
					}

					// retrieve global local tax
					if ($localtax1_type && $localtax1ligne != 0)
						$this->localtax1[$localtax1_type][$localtax1_rate]+=$localtax1ligne;
					if ($localtax2_type && $localtax2ligne != 0)
						$this->localtax2[$localtax2_type][$localtax2_rate]+=$localtax2ligne;

					if (($object->lines[$i]->info_bits & 0x01) == 0x01) $vatrate.='*';
					if (! isset($this->tva[$vatrate])) 				$this->tva[$vatrate]=0;
					$this->tva[$vatrate] += $tvaligne;

					$nexY = max($nexY,$posYAfterImage);

					// Add line
					if (getDolGlobalInt('MAIN_PDF_DASH_BETWEEN_LINES') && $i < ($nblignes - 1))
					{
						$pdf->setPage($pageposafter);
						$pdf->SetLineStyle(array('dash'=>'1,1','color'=>array(80,80,80)));
						//$pdf->SetDrawColor(190,190,200);
						$pdf->line($this->marge_gauche, $nexY+1, $this->page_largeur - $this->marge_droite, $nexY+1);
						$pdf->SetLineStyle(array('dash'=>0));
					}

					$nexY+=2;    // Passe espace entre les lignes

					// Detect if some page were added automatically and output _tableau for past pages
					while ($pagenb < $pageposafter)
					{
						$pdf->setPage($pagenb);
						if ($pagenb == $pageposbeforeprintlines)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf,$object,$outputlangs,1);
						$pagenb++;
						$pdf->setPage($pagenb);
						$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
					}

					if (isset($object->lines[$i+1]->pagebreak) && $object->lines[$i+1]->pagebreak)
					{
						if ($pagenb == $pageposafter)
						{
							$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforfooter, 0, $outputlangs, 0, 1, $object->multicurrency_code);
						}
						else
						{
							$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforfooter, 0, $outputlangs, 1, 1, $object->multicurrency_code);
						}
						$this->_pagefoot($pdf,$object,$outputlangs,1);
						// New page
						$pdf->AddPage();
						if (! empty($tplidx)) $pdf->useTemplate($tplidx);
						$pagenb++;
						if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $this->_pagehead($pdf, $object, 0, $outputlangs);
					}

				}

				// Show square
				if ($pagenb == $pageposbeforeprintlines)
				{
					$this->_tableau($pdf, $tab_top, $this->page_hauteur - $tab_top - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 0, 0, $object->multicurrency_code);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}
				else
				{
					$this->_tableau($pdf, $tab_top_newpage, $this->page_hauteur - $tab_top_newpage - $heightforinfotot - $heightforfreetext - $heightforfooter, 0, $outputlangs, 1, 0, $object->multicurrency_code);
					$bottomlasttab=$this->page_hauteur - $heightforinfotot - $heightforfreetext - $heightforfooter + 1;
				}

				// Affiche zone infos
				$posy=$this->_tableau_info($pdf, $object, $bottomlasttab, $outputlangs);

				// Affiche zone totaux
				$posy = $this->_tableau_tot($pdf, $object, $deja_regle, $bottomlasttab, $outputlangs);

				// Affiche zone versements
				if (($deja_regle || $amount_credit_notes_included || $amount_deposits_included) && !getDolGlobalInt('INVOICE_NO_PAYMENT_DETAILS'))
				{
					$posy = $this->setNewPage($posy, $pdf, $object, $outputlangs,170);

					$posy = $this->_tableau_versements($pdf, $object, $posy, $outputlangs);
				}

				// Pied de page
				$this->_pagefoot($pdf,$object,$outputlangs);
				if (method_exists($pdf,'AliasNbPages')) $pdf->AliasNbPages();

				$pdf->Close();

				$pdf->Output($file,'F');

				// Add pdfgeneration hook
				$hookmanager->initHooks(array('pdfgeneration'));
				$parameters=array('file'=>$file,'object'=>$object,'outputlangs'=>$outputlangs);
				global $action;
				$reshook=$hookmanager->executeHooks('afterPDFCreation',$parameters,$this,$action);    // Note that $action and $object may have been modified by some hooks

				if (getDolGlobalString('MAIN_UMASK'))
					@chmod($file, octdec(getDolGlobalString('MAIN_UMASK')));

				$this->result = array('fullpath'=>$file);

				return 1;   // No error
			}
			else
			{
				$this->error=$langs->transnoentities("ErrorCanNotCreateDir",$dir);
				return 0;
			}
		}
		else
		{
			$this->error=$langs->transnoentities("ErrorConstantNotDefined","FAC_OUTPUTDIR");
			return 0;
		}
	}


	/**
	 *  Show payments table
	 *
	 *  @param	TCPDF			$pdf           Object PDF
	 *  @param  Object		$object         Object invoice
	 *  @param  int			$posy           Position y in PDF
	 *  @param  Translate	$outputlangs    Object langs for output
	 *  @return int             			<0 if KO, >0 if OK
	 */
	function _tableau_versements(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;

		$sign=1;
		if ($object->type == 2 && getDolGlobalInt('INVOICE_POSITIVE_CREDIT_NOTE')) $sign=-1;

		$tab3_posx = 120;
		$tab3_top = $posy + 8;
		$tab3_width = 80;
		$tab3_height = 4;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$tab3_posx -= 20;
		}

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$title=$outputlangs->transnoentities("PaymentsAlreadyDone");
		if ($object->type == 2) $title=$outputlangs->transnoentities("PaymentsBackAlreadyDone");

		$pdf->SetFont('','', $default_font_size - 3);
		$pdf->SetXY($tab3_posx, $tab3_top - 4);
		$pdf->MultiCell(60, 3, $title, 0, 'L', 0);

		$pdf->line($tab3_posx, $tab3_top, $tab3_posx+$tab3_width, $tab3_top);

		$pdf->SetFont('','', $default_font_size - 4);
		$pdf->SetXY($tab3_posx, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Payment"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+21, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Amount"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+40, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Type"), 0, 'L', 0);
		$pdf->SetXY($tab3_posx+58, $tab3_top);
		$pdf->MultiCell(20, 3, $outputlangs->transnoentities("Num"), 0, 'L', 0);

		$pdf->line($tab3_posx, $tab3_top-1+$tab3_height, $tab3_posx+$tab3_width, $tab3_top-1+$tab3_height);

		$y=0;

		$pdf->SetFont('','', $default_font_size - 4);


		// Loop on each deposits and credit notes included
		$sql = "SELECT re.rowid, re.amount_ht, re.multicurrency_amount_ht, re.amount_tva, re.multicurrency_amount_tva,  re.amount_ttc, re.multicurrency_amount_ttc,";
		$sql.= " re.description, re.fk_facture_source,";
		$sql.= " f.type, f.datef";
		$sql.= " FROM ".MAIN_DB_PREFIX ."societe_remise_except as re, ".MAIN_DB_PREFIX ."facture as f";
		$sql.= " WHERE re.fk_facture_source = f.rowid AND re.fk_facture = ".$object->id;
		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i=0;
			$invoice=new Facture($this->db);
			while ($i < $num)
			{
				$y+=3;
				$obj = $this->db->fetch_object($resql);

				if ($obj->type == 2) $text=$outputlangs->trans("CreditNote");
				elseif ($obj->type == 3) $text=$outputlangs->trans("Deposit");
				else $text=$outputlangs->trans("UnknownType");

				$invoice->fetch($obj->fk_facture_source);

				$pdf->SetXY($tab3_posx, $tab3_top+$y);
				$pdf->MultiCell(20, 3, dol_print_date($obj->datef,'day',false,$outputlangs,true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+21, $tab3_top+$y);
				$pdf->MultiCell(20, 3, price((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? $obj->multicurrency_amount_ttc : $obj->amount_ttc, 0, $outputlangs), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+40, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $text, 0, 'L', 0);
				$pdf->SetXY($tab3_posx+58, $tab3_top+$y);
				$pdf->MultiCell(20, 3, $invoice->ref, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3);

				$i++;
			}
		}
		else
		{
			$this->error=$this->db->lasterror();
			return -1;
		}

		// Loop on each payment
		// TODO Call getListOfPaymentsgetListOfPayments instead of hard coded sql
		$sql = "SELECT p.datep as date, p.fk_paiement, p.num_paiement as num, pf.amount as amount, pf.multicurrency_amount,";
		$sql.= " cp.code";
		$sql.= " FROM ".MAIN_DB_PREFIX."paiement_facture as pf, ".MAIN_DB_PREFIX."paiement as p";
		$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."c_paiement as cp ON p.fk_paiement = cp.id";
		$sql.= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = ".$object->id;
		//$sql.= " WHERE pf.fk_paiement = p.rowid AND pf.fk_facture = 1";
		$sql.= " ORDER BY p.datep";

		$resql=$this->db->query($sql);
		if ($resql)
		{
			$num = $this->db->num_rows($resql);
			$i=0;
			while ($i < $num) {
				$y+=3;
				$row = $this->db->fetch_object($resql);

				$pdf->SetXY($tab3_posx, $tab3_top+$y);
				$pdf->MultiCell(20, 3, dol_print_date($this->db->jdate($row->date),'day',false,$outputlangs,true), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+21, $tab3_top+$y);
				$pdf->MultiCell(20, 3, price($sign * ((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? $row->multicurrency_amount : $row->amount), 0, $outputlangs), 0, 'L', 0);
				$pdf->SetXY($tab3_posx+40, $tab3_top+$y);
				$oper = $outputlangs->transnoentitiesnoconv("PaymentTypeShort" . $row->code);

				$pdf->MultiCell(20, 3, $oper, 0, 'L', 0);
				$pdf->SetXY($tab3_posx+58, $tab3_top+$y);
				$pdf->MultiCell(30, 3, $row->num, 0, 'L', 0);

				$pdf->line($tab3_posx, $tab3_top+$y+3, $tab3_posx+$tab3_width, $tab3_top+$y+3);

				$i++;
			}
		}
		else
		{
			$this->error=$this->db->lasterror();
			return -1;
		}

	}


	/**
	 *   Show miscellaneous information (payment mode, payment term, ...)
	 *
	 *   @param		TCPDF			$pdf     		Object PDF
	 *   @param		Object		$object			Object to show
	 *   @param		int			$posy			Y
	 *   @param		Translate	$outputlangs	Langs object
	 *   @return	void
	 */
	function _tableau_info(&$pdf, $object, $posy, $outputlangs)
	{
		global $conf;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$pdf->SetFont('','', $default_font_size - 1);

		// If France, show VAT mention if not applicable
		if ($this->emetteur->country_code == 'FR' && $this->franchise == 1)
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$pdf->MultiCell(100, 3, $outputlangs->transnoentities("VATIsNotUsedForInvoice"), 0, 'L', 0);

			$posy=$pdf->GetY()+4;
		}

		$posxval=52;

		// Show payments conditions
		if ($object->type != 2 && ($object->cond_reglement_code || $object->cond_reglement))
		{
			$pdf->SetFont('','B', $default_font_size - 2);
			$pdf->SetXY($this->marge_gauche, $posy);
			$titre = $outputlangs->transnoentities("PaymentConditions").':';
			$pdf->MultiCell(43, 4, $titre, 0, 'L');

			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posxval, $posy);
			$lib_condition_paiement=$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code)!=('PaymentCondition'.$object->cond_reglement_code)?$outputlangs->transnoentities("PaymentCondition".$object->cond_reglement_code):$outputlangs->convToOutputCharset($object->cond_reglement_doc);
			$lib_condition_paiement=str_replace('\n',"\n",$lib_condition_paiement);
			$pdf->MultiCell(67, 4, $lib_condition_paiement,0,'L');

			$posy=$pdf->GetY()+3;
		}

		if ($object->type != 2)
		{
			// Check a payment mode is defined
			if (empty($object->mode_reglement_code)
				&& !getDolGlobalString('FACTURE_CHQ_NUMBER')
				&& !getDolGlobalString('FACTURE_RIB_NUMBER'))
			{
				$this->error = $outputlangs->transnoentities("ErrorNoPaiementModeConfigured");
			}
			// Avoid having any valid PDF with setup that is not complete
			elseif (($object->mode_reglement_code == 'CHQ' && !getDolGlobalString('FACTURE_CHQ_NUMBER') && empty($object->fk_account) && empty($object->fk_bank))
				|| ($object->mode_reglement_code == 'VIR' && !getDolGlobalString('FACTURE_RIB_NUMBER') && empty($object->fk_account) && empty($object->fk_bank)))
			{
				$outputlangs->load("errors");

				$pdf->SetXY($this->marge_gauche, $posy);
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B', $default_font_size - 2);
				$this->error = $outputlangs->transnoentities("ErrorPaymentModeDefinedToWithoutSetup",$object->mode_reglement_code);
				$pdf->MultiCell(80, 3, $this->error,0,'L',0);
				$pdf->SetTextColor(0,0,0);

				$posy=$pdf->GetY()+1;
			}

			// Show payment mode
			if ($object->mode_reglement_code
				&& $object->mode_reglement_code != 'CHQ'
				&& $object->mode_reglement_code != 'VIR')
			{
				$pdf->SetFont('','B', $default_font_size - 2);
				$pdf->SetXY($this->marge_gauche, $posy);
				$titre = $outputlangs->transnoentities("PaymentMode").':';
				$pdf->MultiCell(80, 5, $titre, 0, 'L');

				$pdf->SetFont('','', $default_font_size - 2);
				$pdf->SetXY($posxval, $posy);
				$lib_mode_reg=$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code)!=('PaymentType'.$object->mode_reglement_code)?$outputlangs->transnoentities("PaymentType".$object->mode_reglement_code):$outputlangs->convToOutputCharset($object->mode_reglement);
				$pdf->MultiCell(80, 5, $lib_mode_reg,0,'L');

				$posy=$pdf->GetY()+2;
			}

			// Show payment mode CHQ
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'CHQ')
			{
				// Si mode reglement non force ou si force a CHQ
				if (getDolGlobalString('FACTURE_CHQ_NUMBER'))
				{
					$diffsizetitle=getDolGlobalInt('PDF_DIFFSIZE_TITLE',3);

					if (getDolGlobalInt('FACTURE_CHQ_NUMBER') > 0)
					{
						$account = new Account($this->db);
						$account->fetch(getDolGlobalInt('FACTURE_CHQ_NUMBER'));

						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$account->proprio),0,'L',0);
						$posy=$pdf->GetY()+1;

						if (!getDolGlobalInt('MAIN_PDF_HIDE_CHQ_ADDRESS'))
						{
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('','', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($account->owner_address), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
						}
					}
					if (getDolGlobalInt('FACTURE_CHQ_NUMBER') == -1)
					{
						$pdf->SetXY($this->marge_gauche, $posy);
						$pdf->SetFont('','B', $default_font_size - $diffsizetitle);
						$pdf->MultiCell(100, 3, $outputlangs->transnoentities('PaymentByChequeOrderedTo',$this->emetteur->name),0,'L',0);
						$posy=$pdf->GetY()+1;

						if (!getDolGlobalInt('MAIN_PDF_HIDE_CHQ_ADDRESS'))
						{
							$pdf->SetXY($this->marge_gauche, $posy);
							$pdf->SetFont('','', $default_font_size - $diffsizetitle);
							$pdf->MultiCell(100, 3, $outputlangs->convToOutputCharset($this->emetteur->getFullAddress()), 0, 'L', 0);
							$posy=$pdf->GetY()+2;
						}
					}
				}
			}

			// If payment mode not forced or forced to VIR, show payment with BAN
			if (empty($object->mode_reglement_code) || $object->mode_reglement_code == 'VIR')
			{
				if (! empty($object->fk_account) || ! empty($object->fk_bank) || getDolGlobalString('FACTURE_RIB_NUMBER'))
				{
					$bankid=(empty($object->fk_account) ? getDolGlobalString('FACTURE_RIB_NUMBER') : $object->fk_account);
					if (! empty($object->fk_bank)) $bankid=$object->fk_bank;   // For backward compatibility when object->fk_account is forced with object->fk_bank
					$account = new Account($this->db);
					$account->fetch($bankid);

					$curx=$this->marge_gauche;
					$cury=$posy;

					$posy=pdf_bank($pdf,$outputlangs,$curx,$cury,$account,0,$default_font_size);

					$posy+=2;
				}
			}
		}

		return $posy;
	}


	/**
	 *	Show total to pay
	 *
	 *	@param	TCPDF			$pdf           Object PDF
	 *	@param  Facture		$object         Object invoice
	 *	@param  int			$deja_regle     Montant deja regle
	 *	@param	int			$posy			Position depart
	 *	@param	Translate	$outputlangs	Objet langs
	 *	@return int							Position pour suite
	 */
	function _tableau_tot(&$pdf, $object, $deja_regle, $posy, $outputlangs)
	{
		global $conf,$mysoc;

		$sign=1;
		if ($object->type == 2 && getDolGlobalInt('INVOICE_POSITIVE_CREDIT_NOTE')) $sign=-1;

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		$tab2_top = $posy;
		$tab2_hl = 4;
		$pdf->SetFont('','', $default_font_size - 1);

		// Tableau total
		$col1x = 120;
		$col2x = 220;
		if ($this->page_largeur < 210) // To work with US executive format
		{
			$col2x-=20;
		}
		$largcol2 = ($this->page_largeur - $this->marge_droite - $col2x);

		$useborder=0;
		$index = 0;



		// pourcentage global d'avancement
		$percent = 0;
		$i=0;
		foreach ($object->lines as $line)
		{
			if(!class_exists('TSubtotal') || !TSubtotal::isModSubtotalLine($line)){
				$percent += $line->situation_percent;
				$i++;
			}
		}
		if(!empty($i)){
			$avancementGlobal = $percent/$i;
		}
		else{
			$avancementGlobal = 0;
		}

		if (empty($object->tab_previous_situation_invoice)) $object->fetchPreviousNextSituationInvoice();
		$TPreviousInvoices = $object->tab_previous_situation_invoice;

		$total_a_payer = 0;
		$total_a_payer_ttc = 0;
		foreach ($TPreviousInvoices as &$previousInvoice){
			$total_a_payer += $previousInvoice->total_ht;
			$total_a_payer_ttc += $previousInvoice->total_ttc;
		}
		$total_a_payer += $object->total_ht;
		$total_a_payer_ttc += $object->total_ttc;

		if(!empty($avancementGlobal)){
			$total_a_payer = $total_a_payer * 100 / $avancementGlobal;
			$total_a_payer_ttc = $total_a_payer_ttc  * 100 / $avancementGlobal;
		}
		else{
			$total_a_payer = 0;
			$total_a_payer_ttc = 0;
		}

		$deja_paye = 0;
		$i = 1;
		if(!empty($TPreviousInvoices)){

			$pdf->setY($tab2_top);
			$posy = $pdf->GetY();




			foreach ($TPreviousInvoices as &$previousInvoice){

				$posy = $this->setNewPage($posy, $pdf, $object, $outputlangs,180);
				// cumul TVA précédent
				$index++;
				$pdf->SetFillColor(255,255,255);
				$pdf->SetXY($col1x, $posy);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("PDFCrabeBtpTitle", $previousInvoice->situation_counter).' '.$outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

				$pdf->SetXY($col2x,$posy);

				$facSign = '';
				if($i>1){
					$facSign = $previousInvoice->total_ht>=0?'+':'';
				}

				$displayAmount = ' '.$facSign.' '.price($previousInvoice->total_ht, 0, $outputlangs);

				$pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

				$i++;
				$deja_paye += $previousInvoice->total_ht;
				$posy += $tab2_hl;

				$pdf->setY($posy);

			}
			$posy = $this->setNewPage($posy,  $pdf, $object, $outputlangs);
			// Display curent total
			$pdf->SetFillColor(255,255,255);
			$pdf->SetXY($col1x, $posy);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("PDFCrabeBtpTitle", $object->situation_counter).' '.$outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

			$pdf->SetXY($col2x,$posy);
			$facSign = '';
			if($i>1){
				$facSign = $object->total_ht>=0?'+':''; // gestion d'un cas particulier client
			}

			if($previousInvoice->type === facture::TYPE_CREDIT_NOTE){
				$facSign = '-'; // les avoirs
			}

			$posy = $this->setNewPage($posy, $pdf, $object, $outputlangs);
			$displayAmount = ' '.$facSign.' '.price($object->total_ht, 0, $outputlangs);
			$pdf->MultiCell($largcol2, $tab2_hl, $displayAmount, 0, 'R', 1);

			$posy += $tab2_hl;
			$posy = $this->setNewPage($posy, $pdf, $object, $outputlangs);
			// Display all total
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->SetFillColor(255,255,255);
			$pdf->SetXY($col1x, $posy);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("BtpTotalProgress", $avancementGlobal), 0, 'L', 1);

			$pdf->SetXY($col2x,$posy);
			$pdf->MultiCell($largcol2, $tab2_hl, price($total_a_payer*$avancementGlobal/100, 0, $outputlangs), 0, 'R', 1);
			$pdf->SetFont('','', $default_font_size - 2);

			$posy += $tab2_hl;

			$posy = $this->setNewPage($posy, $pdf, $object, $outputlangs);
		    $tab2_top = $posy + 3;
			$index=0;

		}

		$pdf->SetFont('','', $default_font_size - 1);
		$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
		// Total HT
		$pdf->SetFillColor(255,255,255);
		$pdf->SetXY($col1x, $tab2_top + 0);
		$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalHT"), 0, 'L', 1);

		$total_ht = (isModEnabled('multicurrency') && $object->multicurrency_tx != 1 ? $object->multicurrency_total_ht : $object->total_ht);
		$pdf->SetXY($col2x, $tab2_top + 0);
		$pdf->MultiCell($largcol2, $tab2_hl, price($sign * ($total_ht + (! empty($object->remise)?$object->remise:0)), 0, $outputlangs), 0, 'R', 1);

		$posy = $this->setNewPage($tab2_top,$pdf, $object, $outputlangs);
		// Show VAT by rates and total
		$pdf->SetFillColor(248,248,248);

		$total_ttc = (isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? $object->multicurrency_total_ttc : $object->total_ttc;

		$this->atleastoneratenotnull=0;
		if (!getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT'))
		{
			$tvaisnull=((! empty($this->tva) && count($this->tva) == 1 && isset($this->tva['0.000']) && is_float($this->tva['0.000'])) ? true : false);
			if (getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_IFNULL') && $tvaisnull)
			{
				// Nothing to do
			}
			else
			{
				// FIXME amount of vat not supported with multicurrency

				//Local tax 1 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach( $this->localtax1 as $localtax_type => $localtax_rate )
				{
					if (in_array((string) $localtax_type, array('1','3','5'))) continue;

					foreach( $localtax_rate as $tvakey => $tvaval )
					{
						if ($tvakey!=0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl='';
							if (preg_match('/\*/',$tvakey))
							{
								$tvakey=str_replace('*','',$tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}

							$totalvat = $outputlangs->transcountrynoentities("TotalLT1",$mysoc->country_code).' ';
							$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
							$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 before VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach( $this->localtax2 as $localtax_type => $localtax_rate )
				{
					if (in_array((string) $localtax_type, array('1','3','5'))) continue;

					foreach( $localtax_rate as $tvakey => $tvaval )
					{
						if ($tvakey!=0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;



							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl='';
							if (preg_match('/\*/',$tvakey))
							{
								$tvakey=str_replace('*','',$tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2",$mysoc->country_code).' ';
							$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
							$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
							$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);

						}
					}
				}

				//}

				// VAT
				foreach($this->tva as $tvakey => $tvaval)
				{
					if ($tvakey != 0)    // On affiche pas taux 0
					{
						$this->atleastoneratenotnull++;

						$index++;
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$tvacompl='';
						if (preg_match('/\*/',$tvakey))
						{
							$tvakey=str_replace('*','',$tvakey);
							$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
						}
						$totalvat =$outputlangs->transcountrynoentities("TotalVAT",$mysoc->country_code).' ';
						$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
						$totalvat.=vatrate($tvakey,1).$tvacompl;
						$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
					}
				}

				//Local tax 1 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX1_OPTION) && $conf->global->FACTURE_LOCAL_TAX1_OPTION=='localtax1on')
				//{
				foreach( $this->localtax1 as $localtax_type => $localtax_rate )
				{
					if (in_array((string) $localtax_type, array('2','4','6'))) continue;

					foreach( $localtax_rate as $tvakey => $tvaval )
					{
						if ($tvakey != 0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl='';
							if (preg_match('/\*/',$tvakey))
							{
								$tvakey=str_replace('*','',$tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT1",$mysoc->country_code).' ';
							$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
							$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
							$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);
							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
				}
				//}
				//Local tax 2 after VAT
				//if (! empty($conf->global->FACTURE_LOCAL_TAX2_OPTION) && $conf->global->FACTURE_LOCAL_TAX2_OPTION=='localtax2on')
				//{
				foreach( $this->localtax2 as $localtax_type => $localtax_rate )
				{
					if (in_array((string) $localtax_type, array('2','4','6'))) continue;

					foreach( $localtax_rate as $tvakey => $tvaval )
					{
						// retrieve global local tax
						if ($tvakey != 0)    // On affiche pas taux 0
						{
							//$this->atleastoneratenotnull++;

							$index++;
							$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

							$tvacompl='';
							if (preg_match('/\*/',$tvakey))
							{
								$tvakey=str_replace('*','',$tvakey);
								$tvacompl = " (".$outputlangs->transnoentities("NonPercuRecuperable").")";
							}
							$totalvat = $outputlangs->transcountrynoentities("TotalLT2",$mysoc->country_code).' ';
							$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
							$totalvat.=vatrate(abs($tvakey),1).$tvacompl;
							$pdf->MultiCell($col2x-$col1x, $tab2_hl, $totalvat, 0, 'L', 1);

							$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
							$pdf->MultiCell($largcol2, $tab2_hl, price($tvaval, 0, $outputlangs), 0, 'R', 1);
						}
					}
					//}
				}

				// Revenue stamp
				if (price2num($object->revenuestamp) != 0)
				{
					$index++;
					$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
					$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RevenueStamp"), $useborder, 'L', 1);

					$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
					$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $object->revenuestamp), $useborder, 'R', 1);
				}

				// Total TTC
				$index++;
				$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->SetTextColor(0,0,60);
				$pdf->SetFillColor(224,224,224);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("TotalTTC"), $useborder, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($sign * $total_ttc, 0, $outputlangs), $useborder, 'R', 1);


				/*if($object->type == Facture::TYPE_SITUATION)
				{
				    // reste à payer total
				    $index++;

				    $pdf->SetFont('','', $default_font_size - 1);
				    $pdf->SetFillColor(255,255,255);
				    $pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				    $pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities('BtpTotalRayToRest'), 0, 'L', 1);

				    $total_ht = ($conf->multicurrency->enabled && $object->multicurrency_tx != 1 ? $object->multicurrency_total_ht : $object->total_ht);
				    $pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				    $pdf->MultiCell($largcol2, $tab2_hl, price($total_a_payer-$deja_paye-$object->total_ht, 0, $outputlangs), 0, 'R', 1);
				}*/


				// Retained warranty
				if(!getDolGlobalInt('USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION')){
					$RetainedWarrantyInvoiceAvailableType = array( Facture::TYPE_SITUATION, Facture::TYPE_STANDARD);
				}else{
					$RetainedWarrantyInvoiceAvailableType = array( Facture::TYPE_SITUATION );
				}


				if( in_array($object->type, $RetainedWarrantyInvoiceAvailableType)
					|| ((!empty($object->situation_final) || !getDolGlobalInt('USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL'))  &&  ( $object->type == Facture::TYPE_SITUATION && !empty($object->retained_warranty)))
				)
				{

					$displayWarranty = $this->displayRetainedWarranty($object);


					if($displayWarranty) {
						$pdf->SetTextColor(40, 40, 40);
						$pdf->SetFillColor(255, 255, 255);

						$retainedWarranty = $this->getRetainedWarrantyAmount($object);

						$billedWithRetainedWarranty = $object->total_ttc - $retainedWarranty ;

						// Billed - retained warranty
						$index++;
						$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("BTPToPayOn", dol_print_date($object->date_lim_reglement, 'day')), $useborder, 'L', 1);

						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($billedWithRetainedWarranty) , $useborder, 'R', 1);

						// retained warranty
						$index++;
						$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
						$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);

						$retainedWarrantyToPayOn = $outputlangs->transnoentities("BTPRetainedWarranty") . ' ('.$object->retained_warranty.'%)';
						$retainedWarrantyToPayOn.=  !empty($object->retained_warranty_date_limit)?' '.$outputlangs->transnoentities("BTPtoPayOn", dol_print_date($object->retained_warranty_date_limit, 'day')):'';

						$pdf->MultiCell($col2x-$col1x, $tab2_hl, $retainedWarrantyToPayOn, $useborder, 'L', 1);
						$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
						$pdf->MultiCell($largcol2, $tab2_hl, price($retainedWarranty) , $useborder, 'R', 1);
					}
				}

			}
		}

		$pdf->SetTextColor(0,0,0);

		$creditnoteamount=$object->getSumCreditNotesUsed((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? 1 : 0);
		$depositsamount=$object->getSumDepositsUsed((isModEnabled('multicurrency') && $object->multicurrency_tx != 1) ? 1 : 0);
		//print "x".$creditnoteamount."-".$depositsamount;exit;
		$resteapayer = price2num($total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 'MT');
		if ($object->paye) $resteapayer=0;

		if (($deja_regle > 0 || $creditnoteamount > 0 || $depositsamount > 0) && !getDolGlobalInt('INVOICE_NO_PAYMENT_DETAILS'))
		{

			// Already paid + Deposits
			$index++;
			$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("Paid"), 0, 'L', 0);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($deja_regle + $depositsamount, 0, $outputlangs), 0, 'R', 0);

			// Credit note
			if ($creditnoteamount)
			{
				$index++;
				$tab2_top = $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("CreditNotes"), 0, 'L', 0);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($creditnoteamount, 0, $outputlangs), 0, 'R', 0);
			}

			// Escompte
			if ($object->close_code == Facture::CLOSECODE_DISCOUNTVAT)
			{
				$index++;
				$tab2_top =  $this->setNewPage($tab2_top, $pdf, $object, $outputlangs);
				$pdf->SetFillColor(255,255,255);

				$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("EscompteOfferedShort"), $useborder, 'L', 1);
				$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
				$pdf->MultiCell($largcol2, $tab2_hl, price($object->total_ttc - $deja_regle - $creditnoteamount - $depositsamount, 0, $outputlangs), $useborder, 'R', 1);

				$resteapayer=0;
			}

			$index++;
			$tab2_top = $this->setNewPage($tab2_top,$pdf, $object, $outputlangs,164);
			$pdf->SetTextColor(0,0,60);
			$pdf->SetFillColor(224,224,224);
			$pdf->SetXY($col1x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($col2x-$col1x, $tab2_hl, $outputlangs->transnoentities("RemainderToPay"), $useborder, 'L', 1);
			$pdf->SetXY($col2x, $tab2_top + $tab2_hl * $index);
			$pdf->MultiCell($largcol2, $tab2_hl, price($resteapayer, 0, $outputlangs), $useborder, 'R', 1);

			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->SetTextColor(0,0,0);
		}

		$index++;
		return ($tab2_top + ($tab2_hl * $index));
	}

	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	function _tableau(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0, $currency='')
	{
		global $conf;

		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) $hidetop=-1;

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top-4);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')) $pdf->Rect($this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_droite-$this->marge_gauche, 5, 'F', null, explode(',',getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
		}

		$pdf->SetDrawColor(128,128,128);
		$pdf->SetFont('','', $default_font_size - 1);

		// Output Rect
		$this->printRect($pdf,$this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height, $hidetop, $hidebottom);	// Rect prend une longueur en 3eme param et 4eme param


		foreach ($this->cols as $colKey => $colDef)
		{
			if(!$this->getColumnStatus($colKey)) continue;

			// get title label
			$colDef['title']['label'] = !empty($colDef['title']['label'])?$colDef['title']['label']:$outputlangs->transnoentities($colDef['title']['textkey']);

			// Add column separator
			if(!empty($colDef['border-left'])){
				$pdf->line($colDef['xStartPos'], $tab_top, $colDef['xStartPos'], $tab_top + $tab_height);
			}

			if (empty($hidetop))
			{
				$pdf->SetXY($colDef['xStartPos'] + $colDef['title']['padding'][3], $tab_top + $colDef['title']['padding'][0] );

				$textWidth = $colDef['width'] - $colDef['title']['padding'][3] -$colDef['title']['padding'][1];
				$pdf->MultiCell($textWidth,2,$colDef['title']['label'],'',$colDef['title']['align']);
			}
		}

		if (empty($hidetop)){
			$pdf->line($this->marge_gauche, $tab_top+5, $this->page_largeur-$this->marge_droite, $tab_top+5);	// line prend une position y en 2eme param et 4eme param
		}


	}

	/**
	 *  Show top header of page.
	 *
	 *  @param	TCPDF			$pdf     		Object PDF
	 *  @param  Object		$object     	Object to show
	 *  @param  int	    	$showaddress    0=no, 1=yes
	 *  @param  Translate	$outputlangs	Object lang for output
	 *  @param  boolean  $showLinkedObject
	 *  @return	void
	 */
	function _pagehead(&$pdf, $object, $showaddress, $outputlangs, $showLinkedObject = TRUE)
	{
		global $conf, $langs;

		// Translations
		$outputlangs->loadLangs(array("main", "bills", "propal", "companies"));

		$default_font_size = pdf_getPDFFontSize($outputlangs);

		pdf_pagehead($pdf,$outputlangs,$this->page_hauteur);

		// Show Draft Watermark
		if($object->statut==Facture::STATUS_DRAFT && (getDolGlobalString('FACTURE_DRAFT_WATERMARK')) )
		{
			pdf_watermark($pdf,$outputlangs,$this->page_hauteur,$this->page_largeur,'mm',getDolGlobalString('FACTURE_DRAFT_WATERMARK'));
		}

		$pdf->SetTextColor(0,0,60);
		$pdf->SetFont('','B', $default_font_size + 3);

		$w = 110;

		$posy=$this->marge_haute;
		$posx=$this->page_largeur-$this->marge_droite-$w;

		$pdf->SetXY($this->marge_gauche,$posy);

		// Logo
		$logo=$conf->mycompany->dir_output.'/logos/'.$this->emetteur->logo;
		if ($this->emetteur->logo)
		{
			if (is_readable($logo))
			{
				$height=pdf_getHeightForLogo($logo);
				$pdf->Image($logo, $this->marge_gauche, $posy, 0, $height);	// width=0 (auto)
			}
			else
			{
				$pdf->SetTextColor(200,0,0);
				$pdf->SetFont('','B',$default_font_size - 2);
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorLogoFileNotFound",$logo), 0, 'L');
				$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ErrorGoToGlobalSetup"), 0, 'L');
			}
		}
		else
		{
			$text=$this->emetteur->name;
			$pdf->MultiCell($w, 4, $outputlangs->convToOutputCharset($text), 0, 'L');
		}

		$pdf->SetFont('','B', $default_font_size + 3);
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$title=$outputlangs->transnoentities("PdfInvoiceTitle");
		if ($object->type == 1) $title=$outputlangs->transnoentities("InvoiceReplacement");
		if ($object->type == 2) $title=$outputlangs->transnoentities("InvoiceAvoir");
		if ($object->type == 3) $title=$outputlangs->transnoentities("InvoiceDeposit");
		if ($object->type == 4) $title=$outputlangs->transnoentities("InvoiceProForma");
		if ($this->situationinvoice) $title=$outputlangs->transnoentities("InvoiceSituation");
		$pdf->MultiCell($w, 3, $title, '', 'R');

		$pdf->SetFont('','B',$default_font_size);

		$posy+=5;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$textref=$outputlangs->transnoentities("Ref")." : " . $outputlangs->convToOutputCharset($object->ref);
		if ($object->statut == Facture::STATUS_DRAFT)
		{
			$pdf->SetTextColor(128,0,0);
			$textref.=' - '.$outputlangs->transnoentities("NotValidated");
		}
		$pdf->MultiCell($w, 4, $textref, '', 'R');

		$posy+=1;
		$pdf->SetFont('','', $default_font_size - 2);

		if ($object->ref_client)
		{
			$posy+=4;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("RefCustomer")." : " . $outputlangs->convToOutputCharset($object->ref_client), '', 'R');
		}

		$objectidnext=$object->getIdReplacingInvoice('validated');
		if ($object->type == 0 && $objectidnext)
		{
			$objectreplacing=new Facture($this->db);
			$objectreplacing->fetch($objectidnext);

			$posy+=3;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementByInvoice").' : '.$outputlangs->convToOutputCharset($objectreplacing->ref), '', 'R');
		}
		if ($object->type == 1)
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=4;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("ReplacementInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}
		if ($object->type == 2 && !empty($object->fk_facture_source))
		{
			$objectreplaced=new Facture($this->db);
			$objectreplaced->fetch($object->fk_facture_source);

			$posy+=3;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CorrectionInvoice").' : '.$outputlangs->convToOutputCharset($objectreplaced->ref), '', 'R');
		}

		$posy+=4;
		$pdf->SetXY($posx,$posy);
		$pdf->SetTextColor(0,0,60);
		$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DateInvoice")." : " . dol_print_date($object->date,"day",false,$outputlangs), '', 'R');

		if (getDolGlobalInt('INVOICE_POINTOFTAX_DATE'))
		{
			$posy+=4;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DatePointOfTax")." : " . dol_print_date($object->date_pointoftax,"day",false,$outputlangs), '', 'R');
		}

		if ($object->type != 2)
		{
			$posy+=3;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("DateDue")." : " . dol_print_date($object->date_lim_reglement,"day",false,$outputlangs,true), '', 'R');
		}

		if ($object->thirdparty->code_client)
		{
			$posy+=3;
			$pdf->SetXY($posx,$posy);
			$pdf->SetTextColor(0,0,60);
			$pdf->MultiCell($w, 3, $outputlangs->transnoentities("CustomerCode")." : " . $outputlangs->transnoentities($object->thirdparty->code_client), '', 'R');
		}

		// Get contact
		if (getDolGlobalInt('DOC_SHOW_FIRST_SALES_REP'))
		{
			$arrayidcontact=$object->getIdContact('internal','SALESREPFOLL');
			if (count($arrayidcontact) > 0)
			{
				$usertmp=new User($this->db);
				$usertmp->fetch($arrayidcontact[0]);
				$posy+=4;
				$pdf->SetXY($posx,$posy);
				$pdf->SetTextColor(0,0,60);
				$pdf->MultiCell($w, 3, $langs->transnoentities("SalesRepresentative")." : ".$usertmp->getFullName($langs), '', 'R');
			}
		}

		$posy+=1;

		$top_shift = 0;
//		// Show list of linked objects
//		$current_y = $pdf->getY();
//
//
//		$object->fetchObjectLinked();
//		// évite le dédoublement de la ref commande si plusieurs objets liés 'commande'
//
//		if (isset($object->linkedObjects['commande']) &&   (($showLinkedObject && is_array($object->linkedObjects['commande']) && count($object->linkedObjects['commande']) > 1) || (is_array($object->linkedObjects['commande']) && count($object->linkedObjects['commande'])) <= 1)){
//			$posy = pdf_writeLinkedObjects($pdf, $object, $outputlangs, $posx, $posy, $w, 3, 'R', $default_font_size);
//		}
//		if ($current_y < $pdf->getY())
//		{
//			$top_shift = $pdf->getY() - $current_y;
//		}

		if ($showaddress)
		{
			// Sender properties
			$carac_emetteur = pdf_build_address($outputlangs, $this->emetteur, $object->thirdparty, '', 0, 'source', $object);

			// Show sender
			$posy=getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy+=$top_shift;
			$posx=$this->marge_gauche;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) $posx=$this->page_largeur-$this->marge_droite-80;

			$hautcadre=getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 38 : 40;
			$widthrecbox=getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 82;


			// Show sender frame
			$pdf->SetTextColor(0,0,0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posx,$posy-5);
			$pdf->MultiCell(66,5, $outputlangs->transnoentities("BillFrom").":", 0, 'L');
			$pdf->SetXY($posx,$posy);
			$pdf->SetFillColor(230,230,230);
			$pdf->MultiCell($widthrecbox, $hautcadre, "", 0, 'R', 1);
			$pdf->SetTextColor(0,0,60);

			// Show sender name
			$pdf->SetXY($posx+2,$posy+3);
			$pdf->SetFont('','B', $default_font_size);
			$pdf->MultiCell($widthrecbox-2, 4, $outputlangs->convToOutputCharset($this->emetteur->name), 0, 'L');
			$posy=$pdf->getY();

			// Show sender information
			$pdf->SetXY($posx+2,$posy);
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->MultiCell($widthrecbox-2, 4, $carac_emetteur, 0, 'L');

			// If BILLING contact defined on invoice, we use it
			$usecontact=false;
			$arrayidcontact=$object->getIdContact('external','BILLING');
			if (count($arrayidcontact) > 0)
			{
				$usecontact=true;
				$result=$object->fetch_contact($arrayidcontact[0]);
			}

			//Recipient name
			// On peut utiliser le nom de la societe du contact
			if ($usecontact && getDolGlobalInt('MAIN_USE_COMPANY_NAME_OF_CONTACT')) {
				$thirdparty = $object->contact;
			} else {
				$thirdparty = $object->thirdparty;
			}

			$carac_client_name= pdfBuildThirdpartyName($thirdparty, $outputlangs);

			$carac_client=pdf_build_address($outputlangs,$this->emetteur,$object->thirdparty,($usecontact?$object->contact:''),$usecontact,'target',$object);

			// Show recipient
			$widthrecbox=getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 92 : 100;
			if ($this->page_largeur < 210) $widthrecbox=84;	// To work with US executive format
			$posy=getDolGlobalInt('MAIN_PDF_USE_ISO_LOCATION') ? 40 : 42;
			$posy+=$top_shift;
			$posx=$this->page_largeur-$this->marge_droite-$widthrecbox;
			if (getDolGlobalInt('MAIN_INVERT_SENDER_RECIPIENT')) $posx=$this->marge_gauche;

			// Show recipient frame
			$pdf->SetTextColor(0,0,0);
			$pdf->SetFont('','', $default_font_size - 2);
			$pdf->SetXY($posx+2,$posy-5);
			$pdf->MultiCell($widthrecbox, 5, $outputlangs->transnoentities("BillTo").":",0,'L');
			$pdf->Rect($posx, $posy, $widthrecbox, $hautcadre);

			// Show recipient name
			$pdf->SetXY($posx+2,$posy+3);
			$pdf->SetFont('','B', $default_font_size);
			$pdf->MultiCell($widthrecbox, 2, $carac_client_name, 0, 'L');

			$posy = $pdf->getY();

			// Show recipient information
			$pdf->SetFont('','', $default_font_size - 1);
			$pdf->SetXY($posx+2,$posy);
			$pdf->MultiCell($widthrecbox, 4, $carac_client, 0, 'L');
		}

		$pdf->SetTextColor(0,0,0);
		return $top_shift;
	}

	/**
	 *   	Show footer of page. Need this->emetteur object
	 *
	 *   	@param	TCPDF			$pdf     			PDF
	 * 		@param	Object		$object				Object to show
	 *      @param	Translate	$outputlangs		Object lang for output
	 *      @param	int			$hidefreetext		1=Hide free text
	 *      @return	int								Return height of bottom margin including footer text
	 */
	function _pagefoot(&$pdf,$object,$outputlangs,$hidefreetext=0)
	{
		global $conf;
		$showdetails=getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_SHOW_FOOT_DETAILS');
		return pdf_pagefoot($pdf,$outputlangs,'INVOICE_FREE_TEXT',$this->emetteur,$this->marge_basse,$this->marge_gauche,$this->page_hauteur,$object,$showdetails,$hidefreetext);
	}




	/**
	 *   	Define Array Column Field
	 *
	 *   	@param	object			$object    		common object
	 *   	@param	outputlangs		$outputlangs    langs
	 *      @param	int			   $hidedetails		Do not show line details
	 *      @param	int			   $hidedesc		Do not show desc
	 *      @param	int			   $hideref			Do not show ref
	 *      @return	null
	 */
	function defineColumnField($object,$outputlangs,$hidedetails=0,$hidedesc=0,$hideref=0){

		global $conf, $hookmanager, $langs;

		// Default field style for content
		$this->defaultContentsFieldsStyle = array(
			'align' => 'R', // R,C,L
			'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		// Default field style for content
		$this->defaultTitlesFieldsStyle = array(
			'align' => 'C', // R,C,L
			'padding' => array(0.5,0,0.5,0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
		);

		/*
		 * For exemple
		$this->cols['theColKey'] = array(
			'rank' => $rank, // int : use for ordering columns
			'width' => 20, // the column width in mm
			'title' => array(
				'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				'label' => ' ', // the final label : used fore final generated text
				'align' => 'L', // text alignement :  R,C,L
				'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L', // text alignement :  R,C,L
				'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
		);
		*/

		$rank=0; // do not use negative rank
		$this->cols['desc'] = array(
			'rank' => $rank,
			'width' => false, // only for desc
			'status' => true,
			'title' => array(
				'textkey' => 'Designation', // use lang key is usefull in somme case with module
				'align' => 'L',
				// 'textkey' => 'yourLangKey', // if there is no label, yourLangKey will be translated to replace label
				// 'label' => ' ', // the final label
				'padding' => array(0.5,0.5,0.5,0.5), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'content' => array(
				'align' => 'L',
			),
		);

		// PHOTO
		$rank = $rank + 10;
		$this->cols['photo'] = array(
			'rank' => $rank,
			'width' => getDolGlobalInt('MAIN_DOCUMENTS_WITH_PICTURE_WIDTH',20), // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'Photo',
				'label' => ' '
			),
			'content' => array(
				'padding' => array(0,0,0,0), // Like css 0 => top , 1 => right, 2 => bottom, 3 => left
			),
			'border-left' => false, // remove left line separator
		);

		if (getDolGlobalInt('MAIN_GENERATE_INVOICES_WITH_PICTURE') && !empty($this->atleastonephoto))
		{
			$this->cols['photo']['status'] = true;
		}


		$rank = $rank + 10;
		$this->cols['vat'] = array(
			'rank' => $rank,
			'status' => false,
			'width' => 16, // in mm
			'title' => array(
				'textkey' => 'VAT'
			),
			'border-left' => true, // add left line separator
		);

		if (!getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT') && !getDolGlobalInt('MAIN_GENERATE_DOCUMENTS_WITHOUT_VAT_COLUMN'))
		{
			$this->cols['vat']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['unit'] = array(
			'rank' => $rank,
			'width' => 11, // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'Unit'
			),
			'border-left' => true, // add left line separator
		);
		if(getDolGlobalInt('PRODUCT_USE_UNITS')){
			$this->cols['unit']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['qty'] = array(
			'rank' => $rank,
			'width' => 13, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'Qty'
			),
			'border-left' => true, // add left line separator
		);

		$rank = $rank + 10;
		$this->cols['subprice'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
			'status' => true,
			'title' => array(
				'textkey' => 'PriceUHT'
			),
			'border-left' => true, // add left line separator
		);



		// BTP SITUATION


		// Colonne "Sommes"
		$rank = $rank + 10;
		$this->cols['btpsomme'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
	        'status' => false,
			'title' => array(
				'textkey' => 'BtpTotHt'
			),
			'border-left' => true, // add left line separator
		);
	    if($this->situationinvoice && ! empty($this->TDataSituation['date_derniere_situation']))
	    {
	        $this->cols['btpsomme']['status'] = true;
	    }

		// Colonne "Progression actuelle"
		$rank = $rank + 10;
		$this->cols['progress_amount'] = array(
			'rank' => $rank,
			'width' => 26, // in mm
			'status' => true,
			'title' => array(
				'textkey' => $langs->transnoentities('PDFCrabeBtpTitle', $object->situation_counter)
			),
			'border-left' => true, // add left line separator
		);
		if($this->situationinvoice)
		{
			$this->cols['progress_amount']['status'] = true;
		}

		// Colonne "Pourcentage Progression actuelle"
		$rank = $rank + 10;
		$this->cols['progress'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
			'status' => false,
			'title' => array(
				'textkey' => $langs->transnoentities('BtpPercentageOfCurrentSituation', $object->situation_counter)
			),
			'border-left' => true, // add left line separator
		);

		if($this->situationinvoice)
		{
			$this->cols['progress']['status'] = true;
		}

		if(!empty($this->TDataSituation['derniere_situation'])) $derniere_situation = $this->TDataSituation['derniere_situation'];

		if(empty($derniere_situation))
		{
			$derniere_situation = 0;
		}

		// Colonne "Progression précédente"
		$rank = $rank + 10;
		$this->cols['prev_progress_amount'] = array(
			'rank' => $rank,
			'width' => 26, // in mm
	        'status' => false,
			'title' => array(
				'textkey' => $langs->transnoentities('PDFCrabeBtpTitle', $derniere_situation->situation_counter ?? 0)
			),
			'border-left' => true, // add left line separator
		);
	    if($this->situationinvoice && ! empty($this->TDataSituation['date_derniere_situation']))
		{
			$this->cols['prev_progress_amount']['status'] = true;
		}

		// Colonne "Pourcentage Progression précédente"
		$rank = $rank + 10;
		$this->cols['prev_progress'] = array(
			'rank' => $rank,
			'width' => 19, // in mm
			'status' => false,
			'title' => array(
				'textkey' => $langs->transnoentities('BtpPercentageOfPreviousSituation', $derniere_situation->situation_counter ?? 0)
			),
			'border-left' => true, // add left line separator
		);

	    if($this->situationinvoice && ! empty($this->TDataSituation['date_derniere_situation']))
		{
			$this->cols['prev_progress']['status'] = true;
		}






		// FIN BTP SITUATION






		$rank = $rank + 10;
		$this->cols['discount'] = array(
			'rank' => $rank,
			'width' => 13, // in mm
			'status' => false,
			'title' => array(
				'textkey' => 'ReductionShort'
			),
			'border-left' => true, // add left line separator
		);
		if ($this->atleastonediscount){
			$this->cols['discount']['status'] = true;
		}

		$rank = $rank + 10;
		$this->cols['totalexcltax'] = array(
			'rank' => $rank,
			'width' => 27, // in mm
			'status' => true,
			'title' => array(
				'textkey' => $langs->transnoentities('BtpTotHTCurrentSituation', $object->situation_counter)
			),
			'border-left' => true, // add left line separator
		);


		$parameters=array(
			'object' => $object,
			'outputlangs' => $outputlangs,
			'hidedetails' => $hidedetails,
			'hidedesc' => $hidedesc,
			'hideref' => $hideref
		);

		$reshook=$hookmanager->executeHooks('defineColumnField',$parameters,$this);    // Note that $object may have been modified by hook
		if ($reshook < 0)
		{
			setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
		}
		elseif (empty($reshook))
		{
			$this->cols = array_replace($this->cols, $hookmanager->resArray); // array_replace is used to preserve keys
		}
		else
		{
			$this->cols = $hookmanager->resArray;
		}

	}



	/*
	 * TODO: remove this following code when this new kind of document are in dolibarr core
	 */


	/**
	 *   	uasort callback function to Sort colums fields
	 *
	 *   	@param	array			$a    			PDF lines array fields configs
	 *   	@param	array			$b    			PDF lines array fields configs
	 *      @return	int								Return compare result
	 */
	function columnSort($a, $b) {

		if(empty($a['rank'])){ $a['rank'] = 0; }
		if(empty($b['rank'])){ $b['rank'] = 0; }
		if ($a['rank'] == $b['rank']) {
			return 0;
		}
		return ($a['rank'] > $b['rank']) ? -1 : 1;

	}

	/**
	 *   	Prepare Array Column Field
	 *
	 *   	@param	object			$object    		common object
	 *   	@param	outputlangs		$outputlangs    langs
	 *      @param		int			$hidedetails		Do not show line details
	 *      @param		int			$hidedesc			Do not show desc
	 *      @param		int			$hideref			Do not show ref
	 *      @return	null
	 */
	function prepareArrayColumnField($object,$outputlangs,$hidedetails=0,$hidedesc=0,$hideref=0){

		global $conf;

		$this->defineColumnField($object,$outputlangs,$hidedetails,$hidedesc,$hideref);


		// Sorting
		uasort ( $this->cols, array( $this, 'columnSort' ) );

		// Positionning
		$curX = $this->page_largeur-$this->marge_droite; // start from right

		// Array witdh
		$arrayWidth = $this->page_largeur-$this->marge_droite-$this->marge_gauche;

		// Count flexible column
		$totalDefinedColWidth = 0;
		$countFlexCol = 0;
		foreach ($this->cols as $colKey =>& $colDef)
		{
			if(!$this->getColumnStatus($colKey)) continue; // continue if desable

			if(!empty($colDef['scale'])){
				// In case of column widht is defined by percentage
				$colDef['width'] = abs($arrayWidth * $colDef['scale'] / 100 );
			}

			if(empty($colDef['width'])){
				$countFlexCol++;
			}
			else{
				$totalDefinedColWidth += $colDef['width'];
			}
		}

		foreach ($this->cols as $colKey =>& $colDef)
		{
			// setting empty conf with default
			if(!empty($colDef['title'])){
				$colDef['title'] = array_replace($this->defaultTitlesFieldsStyle, $colDef['title']);
			}
			else{
				$colDef['title'] = $this->defaultTitlesFieldsStyle;
			}

			// setting empty conf with default
			if(!empty($colDef['content'])){
				$colDef['content'] = array_replace($this->defaultContentsFieldsStyle, $colDef['content']);
			}
			else{
				$colDef['content'] = $this->defaultContentsFieldsStyle;
			}

			if($this->getColumnStatus($colKey))
			{
				// In case of flexible column
				if(empty($colDef['width'])){
					$colDef['width'] = abs(($arrayWidth - $totalDefinedColWidth)) / $countFlexCol;
				}

				// Set positions
				$lastX = $curX;
				$curX = $lastX - $colDef['width'];
				$colDef['xStartPos'] = $curX;
				$colDef['xEndPos']   = $lastX;
			}
		}
	}

	/**
	 *   	get column content width from column key
	 *
	 *   	@param	string			$colKey    		the column key
	 *      @return	float      width in mm
	 */
	function getColumnContentWidth($colKey)
	{
		$colDef = $this->cols[$colKey];
		return  $colDef['width'] - $colDef['content']['padding'][3] - $colDef['content']['padding'][1];
	}


	/**
	 *   	get column content X (abscissa) left position from column key
	 *
	 *   	@param	string    $colKey    		the column key
	 *      @return	float      X position in mm
	 */
	function getColumnContentXStart($colKey)
	{
		$colDef = $this->cols[$colKey];
		return  $colDef['xStartPos'] + $colDef['content']['padding'][3];
	}

	/**
	 *   	get column position rank from column key
	 *
	 *   	@param	string		$colKey    		the column key
	 *      @return	int         rank on success and -1 on error
	 */
	function getColumnRank($colKey)
	{
		if(!isset($this->cols[$colKey]['rank'])) return -1;
		return  $this->cols[$colKey]['rank'];
	}

	/**
	 *   	get column position rank from column key
	 *
	 *   	@param	string		$newColKey    	the new column key
	 *   	@param	array		$defArray    	a single column definition array
	 *   	@param	string		$targetCol    	target column used to place the new column beside
	 *   	@param	bool		$insertAfterTarget    	insert before or after target column ?
	 *      @return	int         new rank on success and -1 on error
	 */
	function insertNewColumnDef($newColKey, $defArray, $targetCol = false, $insertAfterTarget = false)
	{
		// prepare wanted rank
		$rank = -1;

		// try to get rank from target column
		if(!empty($targetCol)){
			$rank = $this->getColumnRank($targetCol);
			if($rank>=0 && $insertAfterTarget){ $rank++; }
		}

		// get rank from new column definition
		if($rank<0 && !empty($defArray['rank'])){
			$rank = $defArray['rank'];
		}

		// error: no rank
		if($rank<0){ return -1; }

		foreach ($this->cols as $colKey =>& $colDef)
		{
			if( $rank <= $colDef['rank'])
			{
				$colDef['rank'] = $colDef['rank'] + 1;
			}
		}

		$defArray['rank'] = $rank;
		$this->cols[$newColKey] = $defArray; // array_replace is used to preserve keys

		return $rank;
	}


	/**
	 *   	print standard column content
	 *
	 *   	@param	TCPDF		    $pdf    	pdf object
	 *   	@param	float		$curY    	curent Y position
	 *   	@param	string		$colKey    	the column key
	 *   	@param	string		$columnText   column text
	 *      @return	int         new rank on success and -1 on error
	 */
	function printStdColumnContent($pdf, &$curY, $colKey, $columnText = '')
	{
		global $hookmanager, $object;

		$parameters=array(
			'object' => $object,
			'curY' =>& $curY,
			'columnText' => $columnText,
			'colKey' => $colKey
		);
		$reshook=$hookmanager->executeHooks('printStdColumnContent',$parameters,$this);    // Note that $action and $object may have been modified by hook
		if ($reshook < 0) setEventMessages($hookmanager->error,$hookmanager->errors,'errors');
		if (!$reshook)
		{
			if(empty($columnText)) return;
			$pdf->SetXY($this->getColumnContentXStart($colKey),$curY); // Set curent position
			$colDef = $this->cols[$colKey];
			$pdf->writeHTMLCell($colDef['width'], 2, $colDef['xStartPos'], $curY, $columnText, 0, 1, 0, true, $colDef['content']['align']);
		}

	}


	/**
	 *   	get column status from column key
	 *
	 *   	@param	string			$colKey    		the column key
	 *      @return	float      width in mm
	 */
	function getColumnStatus($colKey)
	{
		if( !empty($this->cols[$colKey]['status'])){
			return true;
		}
		else  return  false;
	}

	/*
	 * END TODO: remove this when this new kind of document are in dolibarr core
	 */



	/**
	 *   Show table for lines
	 *
	 *   @param		TCPDF			$pdf     		Object PDF
	 *   @param		string		$tab_top		Top position of table
	 *   @param		string		$tab_height		Height of table (rectangle)
	 *   @param		int			$nexY			Y (not used)
	 *   @param		Translate	$outputlangs	Langs object
	 *   @param		int			$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 *   @param		int			$hidebottom		Hide bottom bar of array
	 *   @param		string		$currency		Currency code
	 *   @return	void
	 */
	function _tableauBtp(&$pdf, $tab_top, $tab_height, $nexY, $outputlangs, $hidetop=0, $hidebottom=0, $currency='')
	{
		global $conf, $object, $db;

		$form = new Form($db);

		$tab_height -= 29; // Réduction de la hauteur global du tableau
		$displayWarranty = $this->displayRetainedWarranty($object);
		if(!$displayWarranty){
			$tab_height -= 19; // Réduction de la hauteur global du tableau
		}


		// Force to disable hidetop and hidebottom
		$hidebottom=0;
		if ($hidetop) $hidetop=-1;

		$currency = !empty($currency) ? $currency : $conf->currency;
		$default_font_size = pdf_getPDFFontSize($outputlangs);

		// Amount in (at tab_top - 1)
		$pdf->SetTextColor(0,0,0);
		$pdf->SetFont('','', $default_font_size - 2);

		if (empty($hidetop))
		{
			$titre = $outputlangs->transnoentities("AmountInCurrency",$outputlangs->transnoentitiesnoconv("Currency".$currency));
			$pdf->SetXY($this->page_largeur - $this->marge_droite - ($pdf->GetStringWidth($titre) + 3), $tab_top-4);
			$pdf->MultiCell(($pdf->GetStringWidth($titre) + 3), 2, $titre);

			$width = $this->page_largeur-$this->marge_gauche-$this->marge_droite-83;

			//$conf->global->MAIN_PDF_TITLE_BACKGROUND_COLOR='230,230,230';
			if (getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR'))
			{
				$pdf->Rect($this->posx_new_cumul-1, $tab_top, $width, 5, 'F', null, explode(',',getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
				$pdf->Rect($this->marge_gauche, $tab_top+92.5, $this->page_largeur-$this->marge_gauche-$this->marge_droite, 5, 'F', null, explode(',',getDolGlobalString('MAIN_PDF_TITLE_BACKGROUND_COLOR')));
			}
		}

		$pdf->SetDrawColor(128,128,128);
		$pdf->SetFont('','', $default_font_size - 1);

		// Output Rect
		// KEEPTHIS => Affiche les bords extérieurs
		$this->printRectBtp($pdf,$this->marge_gauche, $tab_top, $this->page_largeur-$this->marge_gauche-$this->marge_droite, $tab_height, $hidetop, $hidebottom);	// Rect prend une longueur en 3eme param et 4eme param

		// PRINT COLUMNS TITLES
		$pdf->line($this->posx_new_cumul-1, $tab_top, $this->posx_new_cumul-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posx_new_cumul-1, $tab_top+0.5);
			$pdf->MultiCell(35,2, $outputlangs->transnoentities("BtpNewCumul"),'','C');
		}

		$pdf->line($this->posx_cumul_anterieur-1, $tab_top, $this->posx_cumul_anterieur-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posx_cumul_anterieur-1, $tab_top+0.5);
			$pdf->MultiCell(35,2, $outputlangs->transnoentities("BtpAnteCumul"),'','C');
		}

		$pdf->line($this->posx_month-1, $tab_top, $this->posx_month-1, $tab_top + $tab_height);
		if (empty($hidetop))
		{
			$pdf->SetXY($this->posx_month-1, $tab_top+0.5);
			$pdf->MultiCell(36,2, $outputlangs->transnoentities("BtpCurrentSituation", $object->situation_counter),'','C');
		}

		// ADD HORIZONTALE LINES
		$pdf->line($this->posx_new_cumul-1, $tab_top+5, $this->page_largeur-$this->marge_droite, $tab_top+5);

		$pdf->line($this->posx_new_cumul-1, $tab_top+20, $this->page_largeur-$this->marge_droite, $tab_top+20);

		$pdf->line($this->marge_gauche, $tab_top+55, $this->page_largeur-$this->marge_droite, $tab_top+55);

		$pdf->line($this->marge_gauche, $tab_top + 65, $this->page_largeur - $this->marge_droite, $tab_top + 65);

		if($displayWarranty) {
			$pdf->line($this->marge_gauche, $tab_top+85, $this->page_largeur-$this->marge_droite, $tab_top+85);
		}


		// ADD TEXT INTO CELL
		/**********************Titres*******************************/
		$pdf->SetXY($this->marge_gauche+2, $tab_top+8);
		$pdf->MultiCell(80,2, $outputlangs->transnoentities("BtpMainWork"),'','L');

		$pdf->SetXY($this->marge_gauche+2, $tab_top+12);
		$pdf->MultiCell(80,2, $outputlangs->transnoentities("BtpAdditionalWork"),'','L');

		$form->load_cache_vatrates("'".$object->thirdparty->country_code."'");

		$i = -8;
		foreach($form->cache_vatrates as $TVatInfo) {
			$tva_tx_formated = sprintf("%01.3f", $TVatInfo['txtva']);
			if(empty($this->TDataSituation['mois'][$tva_tx_formated])) continue;
			$i += 8;

			$pdf->SetXY($this->marge_gauche+2, $tab_top+21 + $i);
			$pdf->MultiCell(80,2, $outputlangs->transnoentities("TotalHT").' '.$TVatInfo['label'],'','C');

			if(! empty($this->TDataSituation['mois'][$tva_tx_formated]['TVA'])) {
				$pdf->SetXY($this->marge_gauche + 2, $tab_top + 25 + $i);
				$pdf->MultiCell(80, 2, $outputlangs->transnoentities("VAT").' '.$TVatInfo['label'], '', 'C');
			}
			else $i -= 4;

		}

		$pdf->SetXY($this->marge_gauche+2, $tab_top+29+$i);
		$pdf->MultiCell(80,2, $outputlangs->transnoentities("TotalTTC"),'','C');


		$pdf->SetFont('','B', $default_font_size - 1);
		$pdf->SetXY($this->marge_gauche+2, $tab_top+58);
		$pdf->MultiCell(80,2, $outputlangs->transnoentities("BtpTotalSituationTTC"),'','C');
		$pdf->SetFont('','', $default_font_size - 2);

		if($displayWarranty){
			$pdf->SetXY($this->marge_gauche + 2, $tab_top + 74);
			$pdf->MultiCell(80, 2, $outputlangs->transnoentities("BtpRetenueGarantie"), '', 'C');
			$nextY = $tab_top+93;
		}
		else{
			$nextY = $tab_top+74;
		}

		$pdf->SetFont('','B', $default_font_size - 1);
		$pdf->SetXY($this->marge_gauche+2, $nextY);
		$pdf->MultiCell(80,2, $outputlangs->transnoentities("BtpRayToRest"),'','L');
		$pdf->SetFont('','', $default_font_size - 2);


		/**********************Données*******************************/
		$TToDisplay = array(
			'nouveau_cumul',
			'cumul_anterieur',
			'mois'
		);

		$x = $this->marge_gauche+85;
		foreach($TToDisplay as $col) {

			// Travaux principaux
			$pdf->SetXY($x, $tab_top+8);
			$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['HT']),'','R');

			// Travaux supplémentaires
			$pdf->SetXY($x, $tab_top+12);
			$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['travaux_sup']),'','R');

			$i = -8;
			foreach($form->cache_vatrates as $TVatInfo) {
				$tva_tx_formated = sprintf("%01.3f", $TVatInfo['txtva']);
				if(empty($this->TDataSituation['mois'][$tva_tx_formated])) continue;
				$i += 8;

				// Total HT
				if(!empty($this->TDataSituation[$col][$tva_tx_formated])){
					$pdf->SetXY($x, $tab_top+21+$i);
					$pdf->MultiCell(32,2, price($this->TDataSituation[$col][$tva_tx_formated]['HT']),'','R');
				}

				// Total TVA
				if(!empty($this->TDataSituation[$col][$tva_tx_formated]) && ! empty($this->TDataSituation['mois'][$tva_tx_formated]['TVA'])) {
					$pdf->SetXY($x, $tab_top + 25 + $i);
					$pdf->MultiCell(32, 2, price($this->TDataSituation[$col][$tva_tx_formated]['TVA']), '', 'R');
				}
				else $i -= 4;

			}

			// Total TTC
			$pdf->SetXY($x, $tab_top+29+$i);
			$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['TTC']),'','R');

			// Total situation
			$pdf->SetFont('','B', $default_font_size - 1);
			$pdf->SetXY($x, $tab_top+58);
			$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['TTC']),'','R');
			$pdf->SetFont('','', $default_font_size - 2);


			if($displayWarranty){
				// Retenue de garantie
				$pdf->SetXY($x, $tab_top+74);
				$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['retenue_garantie']),'','R');
				$nextY = $tab_top+93;
			}
			else{
				$nextY = $tab_top+74;
			}

			// Montant à payer TTC
			$pdf->SetFont('','B', $default_font_size - 1);
			$pdf->SetXY($x, $nextY);
			$pdf->MultiCell(32,2, price($this->TDataSituation[$col]['total_ttc']),'','R');
			$pdf->SetFont('','', $default_font_size - 2);

			$x+=36;

		}
	}



	/**
	 * Checks if an invoice line is a supplementary work.
	 *
	 * Main works: lines of the situation invoice that were already present in the previous invoice.
	 * Supplementary works: lines of the invoice that are not part of invoice S1 (as discussed with Johan, new behavior).
	 * Example:
	 * S1 with l1 (main work), l2 (main work)
	 * S2 with l1 (main work), l2 (main work), l3 (supplementary work)
	 * S3 with l1 (main work), l2 (main work), l3 (supplementary work), l4 (supplementary work)
	 *
	 * @param object $line Invoice detail line (FactureDet)
	 * @return bool True if it's supplementary work, False otherwise
	 */
	function isSupplementaryWork($line): bool {

		// Déclaration d'une CTE (Common Table Expression) récursive.
		// La CTE temporaire "hierarchie_lignes" est utilisée pour parcourir une structure hiérarchique dans la table "facturedet".
		$query = "
		WITH RECURSIVE hierarchie_lignes AS (
			-- Étape 1 : Point de départ de la récursion.
			-- On sélectionne la ligne ayant l'identifiant donné (fk_prev_id) dans la table facturedet.
				SELECT rowid, fk_prev_id, fk_facture
			FROM " . $this->db->prefix() . "facturedet
			WHERE rowid = " . intval($line->fk_prev_id) . "

			UNION ALL

				-- Étape 2 : Récursion.
				-- On recherche les lignes suivantes dans facturedet où l'identifiant correspond à fk_prev_id des résultats précédents.
			SELECT d.rowid, d.fk_prev_id, d.fk_facture
			FROM " . $this->db->prefix() . "facturedet d
			INNER JOIN hierarchie_lignes h ON d.rowid = h.fk_prev_id
		)

		-- Sélection finale : On récupère la facture associée à la dernière ligne de la hiérarchie (celle où fk_prev_id est NULL).
		SELECT fk_facture
		FROM hierarchie_lignes
		WHERE fk_prev_id IS NULL
		LIMIT 1;
		";

		// Execute the query
		$result = $this->db->query($sql);

		// Check the result of the query
		if ($result && $this->db->num_rows($result) > 0) {
			$obj = $this->db->fetch_object($result);
			$fk_facture_origine = $obj->fk_facture;
		} else {
			// If no origin invoice is found, use the current invoice
			$fk_facture_origine = $line->fk_facture;
		}

		// Load the origin invoice to check if it is the first in the cycle
		$tmpFac = new Facture($this->db);
		if ($tmpFac->fetch($fk_facture_origine) > 0) {
			return !$tmpFac->is_first(); // Supplementary work if it's not the first invoice
		}

		return false; // By default, consider it as non-supplementary work
	}


	/**
	 * @param $object Facture
	 * @return array
	 */
	function _getDataSituation(&$object) {

		global $conf;
        $object->fetchPreviousNextSituationInvoice();
		/** @var Facture[] $TPreviousInvoices */
		$TPreviousInvoices = $object->tab_previous_situation_invoice;
        unset($object->tab_previous_situation_invoice);

		$TPreviousInvoices = array_reverse($TPreviousInvoices);
        if(!empty($TPreviousInvoices[0])) $facDerniereSituation = $TPreviousInvoices[0];

        $TDataSituation = array();

        if (! empty($facDerniereSituation))
        {
            $TDataSituation['derniere_situation'] = $facDerniereSituation;
            $TDataSituation['date_derniere_situation'] = $facDerniereSituation->date;
        }

        $retenue_garantie = $retenue_garantie_anterieure = 0;
		// Init tous les champs à 0
		$TDataSituation['cumul_anterieur'] = array(
            'HT' => 0,
            'TVA' => 0,
            'TTC' => 0,
			'retenue_garantie' => $retenue_garantie_anterieure,
			'total_ttc' => - 0,
			'travaux_sup' => 0
		);
		//---------------------------------------
		// FACTURES ANTERIEURES
		//---------------------------------------
		if(!empty($TPreviousInvoices)) {
            $isFirstSituation = false;
            foreach($TPreviousInvoices as $i => $previousInvoice) {
				$TDataSituation['cumul_anterieur']['HT'] += $previousInvoice->total_ht;
				$TDataSituation['cumul_anterieur']['TVA'] += $previousInvoice->total_tva;

				foreach($previousInvoice->lines as $k => $l) {
					$total_ht = floatval($l->total_ht);
					if(empty($total_ht)) continue;

					$prevSituationPercent = 0;
					if (!empty($l->fk_prev_id)){
						$prevSituationPercent = $l->get_prev_progress($previousInvoice->id, true);
					}
					$calc_ht = $l->subprice * $l->qty * (1 - $l->remise_percent/100) * ($l->situation_percent - $prevSituationPercent)/100;


					if ($this->isSupplementaryWork($l) && ! $isFirstSituation) {
						$TDataSituation['cumul_anterieur']['travaux_sup'] += $calc_ht;
					}

					if(! array_key_exists($i+1, $TPreviousInvoices)) $isFirstSituation = true;

					if(! isset($TDataSituation['cumul_anterieur'][$l->tva_tx])) {
						$TDataSituation['cumul_anterieur'][$l->tva_tx] = array('HT' => $calc_ht, 'TVA' => $calc_ht * ($l->tva_tx/100));
					} else {
						$TDataSituation['cumul_anterieur'][$l->tva_tx]['HT'] += $calc_ht;
						$TDataSituation['cumul_anterieur'][$l->tva_tx]['TVA'] += $calc_ht * ($l->tva_tx/100);
					}

				}

				if(! empty($previousInvoice->retained_warranty) && !getDolGlobalInt('USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL')){
					$retenue_garantie_anterieure += $this->getRetainedWarrantyAmount($previousInvoice);
				}
			}
		}

		// Retained warranty
		$retenue_garantie = $this->getRetainedWarrantyAmount($object);
		if($retenue_garantie == -1) $retenue_garantie = 0;

		$TDataSituation['cumul_anterieur']['TTC'] = $TDataSituation['cumul_anterieur']['HT'] + $TDataSituation['cumul_anterieur']['TVA'];
		$TDataSituation['cumul_anterieur']['HT'] -= $TDataSituation['cumul_anterieur']['travaux_sup'];
		$TDataSituation['cumul_anterieur']['total_ttc'] = $TDataSituation['cumul_anterieur']['TTC'] - $retenue_garantie_anterieure;
		$TDataSituation['cumul_anterieur']['retenue_garantie'] = $retenue_garantie_anterieure;

		$nouveau_cumul = $TDataSituation['cumul_anterieur']['HT'];
		$nouveau_cumul_tva = $TDataSituation['cumul_anterieur']['TVA'] + $object->total_tva;

		$TDataSituation['nouveau_cumul'] = array(
			'HT' => $nouveau_cumul,
			'TVA' => $nouveau_cumul_tva,
			'TTC' => $TDataSituation['cumul_anterieur']['TTC'] + $object->total_ttc,
			'travaux_sup' => $TDataSituation['cumul_anterieur']['travaux_sup']
		);

        $TDataSituation['mois'] = array(
            'HT' => 0,
            'TVA' => $object->total_tva,
            'TTC' => $object->total_ht + $object->total_tva,
            'retenue_garantie' => $retenue_garantie,
            'total_ttc' => $object->total_ht + $object->total_tva - $retenue_garantie,
            'travaux_sup' => 0
        );
		//---------------------------------------
		// FACTURE COURANTE
		//---------------------------------------
		foreach($object->lines as $k => $l) {
			$total_ht = floatval($l->total_ht);
			if (empty($total_ht)) continue;

			// Si $prevSituationPercent vaut 0 c'est que la ligne $l est un travail supplémentaire
			$prevSituationPercent = 0;

			if (!empty($l->fk_prev_id)) {
				$prevSituationPercent = $l->get_prev_progress($object->id, true);
			}

			$calc_ht = $l->subprice * $l->qty * (1 - $l->remise_percent/100) * ($l->situation_percent - $prevSituationPercent)/100;
			if(! isset($TDataSituation['nouveau_cumul'][$l->tva_tx])) {
				$TDataSituation['nouveau_cumul'][$l->tva_tx] = array(
					'HT' => ($TDataSituation['cumul_anterieur'][$l->tva_tx]['HT'] ?? 0) + $calc_ht,
					'TVA' => ($TDataSituation['cumul_anterieur'][$l->tva_tx]['TVA'] ?? 0) + $calc_ht * ($l->tva_tx/100)
				);
			}
			else {
				$TDataSituation['nouveau_cumul'][$l->tva_tx]['HT'] += $calc_ht;
				$TDataSituation['nouveau_cumul'][$l->tva_tx]['TVA'] += $calc_ht * ($l->tva_tx/100);
			}

            if(! isset($TDataSituation['mois'][$l->tva_tx])) {
                $TDataSituation['mois'][$l->tva_tx]['HT'] = $calc_ht;
                $TDataSituation['mois'][$l->tva_tx]['TVA'] = $calc_ht * ($l->tva_tx/100);
            }
            else {
                $TDataSituation['mois'][$l->tva_tx]['HT'] += $calc_ht;
                $TDataSituation['mois'][$l->tva_tx]['TVA'] += $calc_ht * ($l->tva_tx/100);
            }


			if (!$this->isSupplementaryWork($l)) {
				$TDataSituation['nouveau_cumul']['HT'] += $calc_ht;
				$TDataSituation['mois']['HT'] += $calc_ht;
			}else{
				$TDataSituation['nouveau_cumul']['travaux_sup'] += $calc_ht;
				$TDataSituation['mois']['travaux_sup'] += $calc_ht;
			}
		}
		$TDataSituation['nouveau_cumul']['retenue_garantie'] = $retenue_garantie + $retenue_garantie_anterieure;
		$TDataSituation['nouveau_cumul']['total_ttc'] = $TDataSituation['nouveau_cumul']['TTC'] - ($retenue_garantie + $retenue_garantie_anterieure);

        // si c'est la première facture de situation alors il n'y a pas de raison que la situation actuelle et le nouveau cumul soit différents.
        if(empty($TDataSituation['derniere_situation'])) {
            $TDataSituation['mois']     = $TDataSituation['nouveau_cumul'];
        }

		return $TDataSituation;
	}

	/**
	 * @param Facture $object
	 * @return bool
	 */
	function displayRetainedWarranty($object){

		if(is_callable(array($object, 'displayRetainedWarranty'))){
			return $object->displayRetainedWarranty();
		}
		else
		{
			// FOR RETROCOMPATIBILITY
			global $conf;

			// TODO : add a flag on invoices to store this conf USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL

			// note : we dont need to test USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION because if $object->retained_warranty is not empty it's because it was set when this conf was active

			$displayWarranty = false;
			if(!empty($object->retained_warranty)) {
				$displayWarranty = true;

				if ($object->type == Facture::TYPE_SITUATION && getDolGlobalInt('USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL')) {
					// Check if this situation invoice is 100% for real
					$displayWarranty = false;
					if (!empty($object->situation_final)) {
						$displayWarranty = true;
					} elseif (!empty($object->lines) && $object->status == Facture::STATUS_DRAFT) {
						// $object->situation_final need validation to be done so this test is need for draft
						$displayWarranty = true;

						foreach ($object->lines as $i => $line) {
							if ($line->product_type < 2 && $line->situation_percent < 100) {
								$displayWarranty = false;
								break;
							}
						}
					}
				}
			}

			return $displayWarranty;

		}
	}

	/**
	 * @param Facture $object
	 * @param int $rounding
	 * @return number or -1 if not available
	 */
	function getRetainedWarrantyAmount($object, $rounding=-1) {

		if(is_callable(array($object, 'getRetainedWarrantyAmount'))){
			//return $object->getRetainedWarrantyAmount($rounding);
			//On surcharge la valeur de la retenue de garantie car quand elle renvoie -1, elle est interprétée comme une valeur monétaire et réduit le total TTC de 1
			$retainedDataReturnValue = $object->getRetainedWarrantyAmount($rounding);

			return $retainedDataReturnValue == -1 ? 0 : $retainedDataReturnValue;
		}
		else
		{

			// FOR RETROCOMPATIBILITY

			global $conf;

			if (empty($object->retained_warranty)) {
				return -1;
			}

			$retainedWarrantyAmount = 0;

			// Billed - retained warranty
			if ($object->type == Facture::TYPE_SITUATION && getDolGlobalInt('USE_RETAINED_WARRANTY_ONLY_FOR_SITUATION_FINAL')) {
				$displayWarranty = true;
				// Check if this situation invoice is 100% for real
				if (!empty($object->lines)) {
					foreach ($object->lines as $i => $line) {
						if ($line->product_type < 2 && $line->situation_percent < 100) {
							$displayWarranty = false;
							break;
						}
					}
				}

				if ($displayWarranty && !empty($object->situation_final)) {
                    if (empty($object->tab_previous_situation_invoice)) $object->fetchPreviousNextSituationInvoice();
					$TPreviousIncoice = $object->tab_previous_situation_invoice;
                    unset($object->tab_previous_situation_invoice);
					$total2BillWT = 0;
					foreach ($TPreviousIncoice as &$fac) {
						$total2BillWT += $fac->total_ttc;
					}
					$total2BillWT += $object->total_ttc;

					$retainedWarrantyAmount = $total2BillWT * $object->retained_warranty / 100;
				} else {
					return -1;
				}

			} else {
				// Because one day retained warranty could be used on standard invoices
				$retainedWarrantyAmount = $object->total_ttc * $object->retained_warranty / 100;
			}

			if ($rounding < 0) {
				$rounding = min(getDolGlobalInt('MAIN_MAX_DECIMALS_UNIT'), getDolGlobalInt('MAIN_MAX_DECIMALS_TOT'));
				return round($retainedWarrantyAmount, 2);
			}

			return $retainedWarrantyAmount;
		}
	}

	function _getInfosLineDerniereSituation(&$object, &$current_line)
	{
		if (empty($object->situation_cycle_ref) || $object->situation_counter <= 1) return;

		$facDerniereSituation = &$this->TDataSituation['derniere_situation'];
		if (empty($facDerniereSituation->lines)) return;
		//var_dump($current_line);exit;
		// On cherche la ligne précédente de la ligne sur laquelle on se trouve :
		$subtotal_ht=0;
		foreach($facDerniereSituation->lines as $l) {
			if ($l->special_code == 9) continue;
			$subtotal_ht += $l->total_ht;
			if(class_exists('TSubtotal') && TSubtotal::isSubtotal($l)){
				$l->total_ht = $subtotal_ht;
				$subtotal_ht = 0;
			}

			if($l->rowid == $current_line->fk_prev_id) {

				// Récupération du total_ht sans prendre en compte la progression (pour la colonne "sommes")
				$tabprice = $this->calcul_price_total($l->qty, $l->subprice, $l->remise_percent, $l->tva_tx, $l->localtax1_tx, $l->localtax2_tx, 0, 'HT', $l->info_bits, $l->product_type);
				$total_ht  = $tabprice[0];
				$total_tva = $tabprice[1];
				$total_ttc = $tabprice[2];
				$total_localtax1 = $tabprice[9];
				$total_localtax2 = $tabprice[10];
				$pu_ht = $tabprice[3];
				//var_dump($tabprice);
				return array(
					'progress_prec'=>$l->situation_percent
				,'total_ht_without_progress'=>$total_ht
				,'total_ht'=>$l->total_ht
				);

			}

		}
	}

	function calcul_price_total($qty, $pu, $remise_percent_ligne, $txtva, $uselocaltax1_rate, $uselocaltax2_rate, $remise_percent_global, $price_base_type, $info_bits, $type, $seller = '',$localtaxes_array='')
	{
		global $conf,$mysoc,$db;

		$result=array();

		// Clean parameters
		if (empty($txtva)) $txtva=0;
		if (empty($seller) || ! is_object($seller))
		{
			dol_syslog("calcul_price_total Warning: function is called with parameter seller that is missing", LOG_WARNING);
			if (! is_object($mysoc))	// mysoc may be not defined (during migration process)
			{
				$mysoc=new Societe($db);
				$mysoc->setMysoc($conf);
			}
			$seller=$mysoc;	// If sell is done to a customer, $seller is not provided, we use $mysoc
			//var_dump($seller->country_id);exit;
		}
		if (empty($localtaxes_array) || ! is_array($localtaxes_array))
		{
			dol_syslog("calcul_price_total Warning: function is called with parameter localtaxes_array that is missing", LOG_WARNING);
		}
		// Too verbose. Enable for debug only
		//dol_syslog("calcul_price_total qty=".$qty." pu=".$pu." remiserpercent_ligne=".$remise_percent_ligne." txtva=".$txtva." uselocaltax1_rate=".$uselocaltax1_rate." uselocaltax2_rate=".$uselocaltax2_rate);

		$countryid=$seller->country_id;
		if ($uselocaltax1_rate < 0) $uselocaltax1_rate=$seller->localtax1_assuj;
		if ($uselocaltax2_rate < 0) $uselocaltax2_rate=$seller->localtax2_assuj;

		// Now we search localtaxes information ourself (rates and types).
		$localtax1_type=0;
		$localtax2_type=0;

		if (is_array($localtaxes_array))
		{
			$localtax1_type = $localtaxes_array[0];
			$localtax1_rate = $localtaxes_array[1];
			$localtax2_type = $localtaxes_array[2];
			$localtax2_rate = $localtaxes_array[3];
		}
		else
		{
			$sql = "SELECT taux, localtax1, localtax2, localtax1_type, localtax2_type";
			$sql.= " FROM ".MAIN_DB_PREFIX."c_tva as cv";
			$sql.= " WHERE cv.taux = ".$txtva;
			$sql.= " AND cv.fk_pays = ".$countryid;
			dol_syslog("calcul_price_total search vat information", LOG_DEBUG);
			$resql = $db->query($sql);
			if ($resql)
			{
				$obj = $db->fetch_object($resql);
				if ($obj)
				{
					$localtax1_rate=$obj->localtax1;
					$localtax2_rate=$obj->localtax2;
					$localtax1_type=$obj->localtax1_type;
					$localtax2_type=$obj->localtax2_type;
					//var_dump($localtax1_rate.' '.$localtax2_rate.' '.$localtax1_type.' '.$localtax2_type);exit;
				}
			}
			else dol_print_error($db);
		}
		// initialize total (may be HT or TTC depending on price_base_type)
		$tot_sans_remise = $pu * $qty;
		$tot_avec_remise_ligne = $tot_sans_remise       * (1 - ($remise_percent_ligne / 100));
		$tot_avec_remise       = $tot_avec_remise_ligne * (1 - ($remise_percent_global / 100));

		// initialize result array
		for ($i=0; $i <= 15; $i++) $result[$i] = 0;

		// if there's some localtax including vat, we calculate localtaxes (we will add later)

		//If input unit price is 'HT', we need to have the totals with main VAT for a correct calculation
		if ($price_base_type != 'TTC')
		{
			$tot_sans_remise_wt = price2num($tot_sans_remise * (1 + ($txtva / 100)),'MU');
			$tot_avec_remise_wt = price2num($tot_avec_remise * (1 + ($txtva / 100)),'MU');
			$pu_wt = price2num($pu * (1 + ($txtva / 100)),'MU');
		}
		else
		{
			$tot_sans_remise_wt = $tot_sans_remise;
			$tot_avec_remise_wt = $tot_avec_remise;
			$pu_wt = $pu;
		}

		//print 'rr'.$price_base_type.'-'.$txtva.'-'.$tot_sans_remise_wt."-".$pu_wt."-".$uselocaltax1_rate."-".$localtax1_rate."-".$localtax1_type."\n";

		$localtaxes = array(0,0,0);
		$apply_tax = false;
		switch($localtax1_type) {
			case '2':     // localtax on product or service
				$apply_tax = true;
				break;
			case '4':     // localtax on product
				if ($type == 0) $apply_tax = true;
				break;
			case '6':     // localtax on service
				if ($type == 1) $apply_tax = true;
				break;
		}
		if ($uselocaltax1_rate && $apply_tax) {
			$result[14] = price2num(($tot_sans_remise_wt * (1 + ( $localtax1_rate / 100))) - $tot_sans_remise_wt, 'MT');
			$localtaxes[0] += $result[14];

			$result[9] = price2num(($tot_avec_remise_wt * (1 + ( $localtax1_rate / 100))) - $tot_avec_remise_wt, 'MT');
			$localtaxes[1] += $result[9];

			$result[11] = price2num(($pu_wt * (1 + ( $localtax1_rate / 100))) - $pu_wt, 'MU');
			$localtaxes[2] += $result[11];
		}

		$apply_tax = false;
		switch($localtax2_type) {
			case '2':     // localtax on product or service
				$apply_tax = true;
				break;
			case '4':     // localtax on product
				if ($type == 0) $apply_tax = true;
				break;
			case '6':     // localtax on service
				if ($type == 1) $apply_tax = true;
				break;
		}
		if ($uselocaltax2_rate && $apply_tax) {
			$result[15] = price2num(($tot_sans_remise_wt * (1 + ( $localtax2_rate / 100))) - $tot_sans_remise_wt, 'MT');
			$localtaxes[0] += $result[15];

			$result[10] = price2num(($tot_avec_remise_wt * (1 + ( $localtax2_rate / 100))) - $tot_avec_remise_wt, 'MT');
			$localtaxes[1] += $result[10];

			$result[12] = price2num(($pu_wt * (1 + ( $localtax2_rate / 100))) - $pu_wt, 'MU');
			$localtaxes[2] += $result[12];
		}

		//dol_syslog("price.lib::calcul_price_total $qty, $pu, $remise_percent_ligne, $txtva, $price_base_type $info_bits");
		if ($price_base_type == 'HT')
		{
			// We work to define prices using the price without tax
			$result[6] = price2num($tot_sans_remise, 'MT');
			$result[8] = price2num($tot_sans_remise * (1 + ( (($info_bits & 1)?0:$txtva) / 100)) + $localtaxes[0], 'MT');	// Selon TVA NPR ou non
			$result8bis= price2num($tot_sans_remise * (1 + ( $txtva / 100)) + $localtaxes[0], 'MT');	// Si TVA consideree normale (non NPR)
			$result[7] = price2num($result8bis - ($result[6] + $localtaxes[0]), 'MT');

			$result[0] = price2num($tot_avec_remise, 'MT');
			$result[2] = price2num($tot_avec_remise * (1 + ( (($info_bits & 1)?0:$txtva) / 100)) + $localtaxes[1], 'MT');	// Selon TVA NPR ou non
			$result2bis= price2num($tot_avec_remise * (1 + ( $txtva / 100)) + $localtaxes[1], 'MT');	// Si TVA consideree normale (non NPR)
			$result[1] = price2num($result2bis - ($result[0] + $localtaxes[1]), 'MT');	// Total VAT = TTC - (HT + localtax)

			$result[3] = price2num($pu, 'MU');
			$result[5] = price2num($pu * (1 + ( (($info_bits & 1)?0:$txtva) / 100)) + $localtaxes[2], 'MU');	// Selon TVA NPR ou non
			$result5bis= price2num($pu * (1 + ($txtva / 100)) + $localtaxes[2], 'MU');	// Si TVA consideree normale (non NPR)
			$result[4] = price2num($result5bis - ($result[3] + $localtaxes[2]), 'MU');
		}
		else
		{
			// We work to define prices using the price with tax
			$result[8] = price2num($tot_sans_remise + $localtaxes[0], 'MT');
			$result[6] = price2num($tot_sans_remise / (1 + ((($info_bits & 1)?0:$txtva) / 100)), 'MT');	// Selon TVA NPR ou non
			$result6bis= price2num($tot_sans_remise / (1 + ($txtva / 100)), 'MT');	// Si TVA consideree normale (non NPR)
			$result[7] = price2num($result[8] - ($result6bis + $localtaxes[0]), 'MT');

			$result[2] = price2num($tot_avec_remise + $localtaxes[1], 'MT');
			$result[0] = price2num($tot_avec_remise / (1 + ((($info_bits & 1)?0:$txtva) / 100)), 'MT');	// Selon TVA NPR ou non
			$result0bis= price2num($tot_avec_remise / (1 + ($txtva / 100)), 'MT');	// Si TVA consideree normale (non NPR)
			$result[1] = price2num($result[2] - ($result0bis + $localtaxes[1]), 'MT');	// Total VAT = TTC - (HT + localtax)

			$result[5] = price2num($pu + $localtaxes[2], 'MU');
			$result[3] = price2num($pu / (1 + ((($info_bits & 1)?0:$txtva) / 100)), 'MU');	// Selon TVA NPR ou non
			$result3bis= price2num($pu / (1 + ($txtva / 100)), 'MU');	// Si TVA consideree normale (non NPR)
			$result[4] = price2num($result[5] - ($result3bis + $localtaxes[2]), 'MU');
		}

		// if there's some localtax without vat, we calculate localtaxes (we will add them at end)

		//If input unit price is 'TTC', we need to have the totals without main VAT for a correct calculation
		if ($price_base_type == 'TTC')
		{
			$tot_sans_remise= price2num($tot_sans_remise / (1 + ($txtva / 100)),'MU');
			$tot_avec_remise= price2num($tot_avec_remise / (1 + ($txtva / 100)),'MU');
			$pu = price2num($pu / (1 + ($txtva / 100)),'MU');
		}

		$apply_tax = false;
		switch($localtax1_type) {
			case '1':     // localtax on product or service
				$apply_tax = true;
				break;
			case '3':     // localtax on product
				if ($type == 0) $apply_tax = true;
				break;
			case '5':     // localtax on service
				if ($type == 1) $apply_tax = true;
				break;
		}
		if ($uselocaltax1_rate && $apply_tax) {
			$result[14] = price2num(($tot_sans_remise * (1 + ( $localtax1_rate / 100))) - $tot_sans_remise, 'MT');	// amount tax1 for total_ht_without_discount
			$result[8] += $result[14];																				// total_ttc_without_discount + tax1

			$result[9] = price2num(($tot_avec_remise * (1 + ( $localtax1_rate / 100))) - $tot_avec_remise, 'MT');	// amount tax1 for total_ht
			$result[2] += $result[9];																				// total_ttc + tax1

			$result[11] = price2num(($pu * (1 + ( $localtax1_rate / 100))) - $pu, 'MU');							// amount tax1 for pu_ht
			$result[5] += $result[11];																				// pu_ht + tax1
		}

		$apply_tax = false;
		switch($localtax2_type) {
			case '1':     // localtax on product or service
				$apply_tax = true;
				break;
			case '3':     // localtax on product
				if ($type == 0) $apply_tax = true;
				break;
			case '5':     // localtax on service
				if ($type == 1) $apply_tax = true;
				break;
		}
		if ($uselocaltax2_rate && $apply_tax) {
			$result[15] = price2num(($tot_sans_remise * (1 + ( $localtax2_rate / 100))) - $tot_sans_remise, 'MT');	// amount tax2 for total_ht_without_discount
			$result[8] += $result[15];																				// total_ttc_without_discount + tax2

			$result[10] = price2num(($tot_avec_remise * (1 + ( $localtax2_rate / 100))) - $tot_avec_remise, 'MT');	// amount tax2 for total_ht
			$result[2] += $result[10];																				// total_ttc + tax2

			$result[12] = price2num(($pu * (1 + ( $localtax2_rate / 100))) - $pu, 'MU');							// amount tax2 for pu_ht
			$result[5] += $result[12];																				// pu_ht + tax2
		}

		// If rounding is not using base 10 (rare)
        $roundingRule = getDolGlobalInt('MAIN_ROUNDING_RULE_TOT');
		if ($roundingRule > 0)
		{
			if ($price_base_type == 'HT')
			{
				$result[0]  = round($result[0] / $roundingRule, 0) * $roundingRule;
				$result[1]  = round($result[1] / $roundingRule, 0) * $roundingRule;
				$result[2]  = price2num($result[0]+$result[1], 'MT');
				$result[9]  = round($result[9] / $roundingRule, 0) * $roundingRule;
				$result[10] = round($result[10] / $roundingRule, 0) * $roundingRule;
			}
			else
			{
				$result[1]  = round($result[1] / $roundingRule, 0) * $roundingRule;
				$result[2]  = round($result[2] / $roundingRule, 0) * $roundingRule;
				$result[0]  = price2num($result[2]-$result[0], 'MT');
				$result[9]  = round($result[9] / $roundingRule, 0) * $roundingRule;
				$result[10] = round($result[10] / $roundingRule, 0) * $roundingRule;
			}
		}

		// initialize result array
		//for ($i=0; $i <= 15; $i++) $result[$i] = (float) $result[$i];

		dol_syslog('Price.lib::calcul_price_total MAIN_ROUNDING_RULE_TOT=' . getDolGlobalInt('MAIN_ROUNDING_RULE_TOT').' pu='.$pu.' qty='.$qty.' price_base_type='.$price_base_type.' total_ht='.$result[0].'-total_vat='.$result[1].'-total_ttc='.$result[2]);

		return $result;
	}


	/**
	 * Rect pdf
	 *
	 * @param	TCPDF		$pdf			Object PDF
	 * @param	float	$x				Abscissa of first point
	 * @param	float	$y		        Ordinate of first point
	 * @param	float	$l				??
	 * @param	float	$h				??
	 * @param	int		$hidetop		1=Hide top bar of array and title, 0=Hide nothing, -1=Hide only title
	 * @param	int		$hidebottom		Hide bottom
	 * @return	void
	 */
	function printRectBtp($pdf, $x, $y, $l, $h, $hidetop=0, $hidebottom=0)
	{
		if (empty($hidetop) || $hidetop==-1) $pdf->line($x, $y, $x+$l, $y);
		$pdf->line($x+$l, $y, $x+$l, $y+$h);
		if (empty($hidebottom)) $pdf->line($x+$l, $y+$h, $x, $y+$h);
		$pdf->line($x, $y+$h, $x, $y);
	}

	/**
	 * @param $posy
	 * @param $pdf

	 * @param $object
	 * @param $outputlangs
	 * @return array
	 */
	public function setNewPage($posy,   &$pdf, &$object, $outputlangs,$maxY = 168)
	{
		global $conf;

		if ($posy > $maxY) {
			$this->_pagefoot($pdf,$object,$outputlangs,1);
			$pdf->addPage();
			$pdf->setY($this->marge_haute);
			if (!getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD')) $posy = $this->_pagehead($pdf, $object, 0, $outputlangs);
			$posy = (getDolGlobalInt('MAIN_PDF_DONOTREPEAT_HEAD') ? 10 : 42);
		}

		return $posy;
	}


}
