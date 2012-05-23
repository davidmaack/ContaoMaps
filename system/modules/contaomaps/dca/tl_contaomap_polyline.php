<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * PHP version 5
 * @copyright  Cyberspectrum 2012
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @package    ContaoMaps
 * @license    LGPL 
 * @filesource
 */

/**
 * Table structure for tl_contaomap_polyline
 */

$GLOBALS['TL_DCA']['tl_contaomap_polyline'] = array
(
	// Config
	'config' => array
	(
		'dataContainer'					=> 'Table',
		'ptable'						=> 'tl_contaomap_layer',
		'enableVersioning'				=> false,
	),

	// List
	'list' => array
	(
		'sorting' => array
		(
			'mode'						=> 1,
			'fields'					=> array('name'),
			'flag'						=> 3,
			'panelLayout'				=> 'filter;search,limit',
		),
		'label' => array
		(
			'fields'					=> array('name'),
			'format'					=> '%s'
		),
		'global_operations' => array
		(
			'all' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['MSC']['all'],
				'href'					=> 'act=select',
				'class'					=> 'header_edit_all',
				'attributes'			=> 'onclick="Backend.getScrollOffset();"'
			)
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['edit'],
				'href'					=> 'act=edit',
				'icon'					=> 'edit.gif',
			),
			'copy' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['copy'],
				'href'					=> 'act=paste&amp;mode=copy',
				'icon'					=> 'copy.gif',
			),
			'cut' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['cut'],
				'href'					=> 'act=paste&amp;mode=cut',
				'icon'					=> 'cut.gif',
			),
			'delete' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['delete'],
				'href'					=> 'act=delete',
				'icon'					=> 'delete.gif',
				'attributes'			=> 'onclick="if (!confirm(\'' . $GLOBALS['TL_LANG']['MSC']['deleteConfirm'] . '\')) return false; Backend.getScrollOffset();"',
			),
			'toggle' => array
			(
				'label'               => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['toggle'],
				'icon'                => 'visible.gif',
				'attributes'          => 'onclick="Backend.getScrollOffset(); return AjaxRequest.toggleVisibility(this, %s);"',
				'button_callback'     => array('tl_contaomap_polyline', 'toggleIcon')
			),
			'show' => array
			(
				'label'					=> &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['show'],
				'href'					=> 'act=show',
				'icon'					=> 'show.gif'
			),
		)
	),

	// Palettes
	'palettes' => array
	(
		'default'						=> '{title_legend},name,published,strokeweight,strokecolor,strokeopacity,fillcolor,fillopacity,coords'
	),

	// Fields
	'fields' => array
	(
		'name' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['name'],
			'exclude'                 => true,
			'inputType'               => 'text',
			'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class' => 'w50')
		),
		'published' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['published'],
			'exclude'                 => true,
			'filter'                  => true,
			'flag'                    => 1,
			'inputType'               => 'checkbox',
			'eval'                    => array('doNotCopy'=>true)
		),
		'coords' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['coords'],
			'inputType'               => 'polyline',
			'search'                  => false,
			'eval'                    => array('mandatory'=>true, 'alwaysSave' => true),
			'save_callback' => array(array('tl_contaomap_polyline', 'onSave')),
		),
		'strokecolor' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['strokecolor'],
			'inputType'               => 'text',
			'eval'                    => array('maxlength'=>6, 'isHexColor'=>true, 'decodeEntities'=>true, 'tl_class'=>'w50 wizard'),
			'wizard' => array
			(
				array('tl_contaomap_polyline', 'colorPicker')
			)
		),
		'strokeweight' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['strokeweight'],
			'inputType'               => 'text',
			'eval'                    => array('rgxp' =>'digit', 'tl_class'=>'w50 wizard'),
		),
		'strokeopacity' => array
		(
			'label'                   => &$GLOBALS['TL_LANG']['tl_contaomap_polyline']['strokeopacity'],
			'inputType'               => 'text',
			'eval'                    => array('rgxp' =>'digit', 'tl_class'=>'w50 wizard'),
		),
	)
);


class tl_contaomap_polyline extends Backend
{
	/**
	 * Add the mooRainbow scripts to the page
	 */
	public function __construct()
	{
		parent::__construct();

		$GLOBALS['TL_CSS'][] = 'plugins/mootools/rainbow.css?'. MOO_RAINBOW . '|screen';
		$GLOBALS['TL_JAVASCRIPT'][] = 'plugins/mootools/rainbow.js?' . MOO_RAINBOW;

		$this->import('BackendUser', 'User');
	}

	/**
	 * Return the color picker wizard
	 * @param object
	 * @return string
	 */
	public function colorPicker(DataContainer $dc)
	{
		return ' ' . $this->generateImage('pickcolor.gif', $GLOBALS['TL_LANG']['MSC']['colorpicker'], 'style="vertical-align:top; cursor:pointer;" id="moo_'.$dc->field.'" class="mooRainbow"');
	}

	/*
	 * Helper function to transfer coordinates into the separate columns
	 */
	public function onSave($varValue, DataContainer $dc) {
		// migrate values to location table.
		$arrExtends = ContaoMap::calcExtends($varValue);
		$this->Database->prepare('UPDATE tl_contaomap_polyline %s WHERE id=?')
						->set(array(
							'min_latitude' => $arrExtends[0][0],
							'min_longitude'=> $arrExtends[0][1],
							'max_latitude'=> $arrExtends[1][0],
							'max_longitude'=> $arrExtends[1][1]
						))
						->execute($dc->id);
		return $varValue;
	}

	/**
	 * Check permissions to edit table tl_news
	 */
	public function checkPermission()
	{

		if ($this->User->isAdmin)
		{
			return;
		}

		// Set root IDs
		if (!is_array($this->User->maplayer) || count($this->User->maplayer) < 1)
		{
			$root = array(0);
		}
		else
		{
			$root = $this->User->maplayer;
		}

		$id = strlen($this->Input->get('id')) ? $this->Input->get('id') : CURRENT_ID;

		// Check current action
		switch ($this->Input->get('act'))
		{
			case 'paste':
				// Allow
				break;

			case 'create':
				if (!strlen($this->Input->get('pid')) || !in_array($this->Input->get('pid'), $root))
				{
					$this->log('Not enough permissions to create polylines in layer ID "'.$this->Input->get('pid').'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}
				break;

			case 'cut':
			case 'copy':
				if (!in_array($this->Input->get('pid'), $root))
				{
					$this->log('Not enough permissions to '.$this->Input->get('act').' polyline "'.$id.'" to layer ID "'.$this->Input->get('pid').'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}
				// NO BREAK STATEMENT HERE

			case 'edit':
			case 'show':
			case 'delete':
			case 'toggle':
				$objArchive = $this->Database->prepare("SELECT pid FROM tl_contaomap_polyline WHERE id=?")
											 ->limit(1)
											 ->execute($id);

				if ($objArchive->numRows < 1)
				{
					$this->log('Invalid polyline ID "'.$id.'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}

				if (!in_array($objArchive->pid, $root))
				{
					$this->log('Not enough permissions to '.$this->Input->get('act').' polyline ID "'.$id.'" of layer ID "'.$objArchive->pid.'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}
				break;

			case 'select':
			case 'editAll':
			case 'deleteAll':
			case 'overrideAll':
			case 'cutAll':
			case 'copyAll':
				if (!in_array($id, $root))
				{
					$this->log('Not enough permissions to access layer ID "'.$id.'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}

				$objArchive = $this->Database->prepare("SELECT id FROM tl_contaomap_polyline WHERE pid=?")
											 ->execute($id);

				if ($objArchive->numRows < 1)
				{
					$this->log('Invalid layer ID "'.$id.'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}

				$session = $this->Session->getData();
				$session['CURRENT']['IDS'] = array_intersect($session['CURRENT']['IDS'], $objArchive->fetchEach('id'));
				$this->Session->setData($session);
				break;

			default:
				if (strlen($this->Input->get('act')))
				{
					$this->log('Invalid command "'.$this->Input->get('act').'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}
				elseif (!in_array($id, $root))
				{
					$this->log('Not enough permissions to access layer ID "'.$id.'"', 'tl_contaomap_polyline checkPermission', TL_ERROR);
					$this->redirect('contao/main.php?act=error');
				}
				break;
		}
	}

	/**
	 * Return the "toggle visibility" button
	 * @param array
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @param string
	 * @return string
	 */
	public function toggleIcon($row, $href, $label, $title, $icon, $attributes)
	{
		if (strlen($this->Input->get('tid')))
		{
			$this->toggleVisibility($this->Input->get('tid'), ($this->Input->get('state') == 1));
			$this->redirect($this->getReferer());
		}

		// Check permissions AFTER checking the tid, so hacking attempts are logged
		if (!$this->User->isAdmin && !$this->User->hasAccess('tl_contaomap_polyline::published', 'alexf'))
		{
			return '';
		}

		$href .= '&amp;tid='.$row['id'].'&amp;state='.($row['published'] ? '' : 1);

		if (!$row['published'])
		{
			$icon = 'invisible.gif';
		}		

		return '<a href="'.$this->addToUrl($href).'" title="'.specialchars($title).'"'.$attributes.'>'.$this->generateImage($icon, $label).'</a> ';
	}

	/**
	 * Disable/enable a user group
	 * @param integer
	 * @param boolean
	 */
	public function toggleVisibility($intId, $blnVisible)
	{
		// Check permissions to edit
		$this->Input->setGet('id', $intId);
		$this->Input->setGet('act', 'toggle');
		$this->checkPermission();
		// Check permissions to publish
		if (!$this->User->isAdmin && !$this->User->hasAccess('tl_contaomap_polyline::published', 'alexf'))
		{
			$this->log('Not enough permissions to publish/unpublish news item ID "'.$intId.'"', 'tl_contaomap_polyline toggleVisibility', TL_ERROR);
			$this->redirect('contao/main.php?act=error');
		}
		$this->createInitialVersion('tl_contaomap_polyline', $intId);
		// Trigger the save_callback
		if (is_array($GLOBALS['TL_DCA']['tl_news']['fields']['published']['save_callback']))
		{
			foreach ($GLOBALS['TL_DCA']['tl_news']['fields']['published']['save_callback'] as $callback)
			{
				$this->import($callback[0]);
				$blnVisible = $this->$callback[0]->$callback[1]($blnVisible, $this);
			}
		}
		// Update the database
		$this->Database->prepare("UPDATE tl_contaomap_polyline SET tstamp=". time() .", published='" . ($blnVisible ? 1 : '') . "' WHERE id=?")
					   ->execute($intId);
		$this->createNewVersion('tl_contaomap_polyline', $intId);
	}
}

?>