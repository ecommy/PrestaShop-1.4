<?php
/*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2013 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class StatsBestProducts extends ModuleGrid
{
	private $_html = null;
	private $_query =  null;
	private $_columns = null;
	private $_defaultSortColumn = null;
	private $_defaultSortDirection = null;
	private $_emptyMessage = null;
	private $_pagingMessage = null;
	
	function __construct()
	{
		$this->name = 'statsbestproducts';
		$this->tab = 'analytics_stats';
		$this->version = 1.0;
		$this->author = 'PrestaShop';
		$this->need_instance = 0;
		
		$this->_defaultSortColumn = 'totalPriceSold';
		$this->_defaultSortDirection = 'DESC';
		$this->_emptyMessage = $this->l('Empty recordset returned');
		$this->_pagingMessage = $this->l('Displaying').' {0} - {1} '.$this->l('of').' {2}';
		
		$this->_columns = array(
			array(
				'id' => 'reference',
				'header' => $this->l('Ref.'),
				'dataIndex' => 'reference',
				'align' => 'left',
				'width' => 50
			),
			array(
				'id' => 'name',
				'header' => $this->l('Name'),
				'dataIndex' => 'name',
				'align' => 'left',
				'width' => 100
			),
			array(
				'id' => 'totalQuantitySold',
				'header' => $this->l('Quantity sold'),
				'dataIndex' => 'totalQuantitySold',
				'width' => 50,
				'align' => 'right'
			),
			array(
				'id' => 'avgPriceSold',
				'header' => $this->l('Price sold'),
				'dataIndex' => 'avgPriceSold',
				'width' => 50,
				'align' => 'right'
			),
			array(
				'id' => 'totalPriceSold',
				'header' => $this->l('Sales'),
				'dataIndex' => 'totalPriceSold',
				'width' => 50,
				'align' => 'right'
			),
			array(
				'id' => 'averageQuantitySold',
				'header' => $this->l('Quantity sold/ day'),
				'dataIndex' => 'averageQuantitySold',
				'width' => 60,
				'align' => 'right'
			),
			array(
				'id' => 'totalPageViewed',
				'header' => $this->l('Page viewed'),
				'dataIndex' => 'totalPageViewed',
				'width' => 60,
				'align' => 'right'
			),
			array(
				'id' => 'quantity',
				'header' => $this->l('Stock'),
				'dataIndex' => 'quantity',
				'width' => 50,
				'align' => 'right'
			)
		);
		
		parent::__construct();
		
		$this->displayName = $this->l('Best products');
		$this->description = $this->l('A list of the best products');
	}
	
	public function install()
	{
		return (parent::install() AND $this->registerHook('AdminStatsModules'));
	}
	
	public function hookAdminStatsModules($params)
	{
		$engineParams = array(
			'id' => 'id_product',
			'title' => $this->displayName,
			'columns' => $this->_columns,
			'defaultSortColumn' => $this->_defaultSortColumn,
			'defaultSortDirection' => $this->_defaultSortDirection,
			'emptyMessage' => $this->_emptyMessage,
			'pagingMessage' => $this->_pagingMessage
		);

		if (Tools::getValue('export'))
			$this->csvExport($engineParams);
				
		$this->_html = '
		<fieldset class="width3"><legend><img src="../modules/'.$this->name.'/logo.gif" /> '.$this->displayName.'</legend>
			'.ModuleGrid::engine($engineParams).'
			<p><a href="'.htmlentities($_SERVER['REQUEST_URI']).'&export=1"><img src="../img/admin/asterisk.gif" />'.$this->l('CSV Export').'</a></p>
		</fieldset>';
		return $this->_html;
	}
		
	public function getData()
	{
		$dateBetween = $this->getDate();
		$arrayDateBetween = explode(' AND ', $dateBetween);

		$this->_query = '
		SELECT SQL_CALC_FOUND_ROWS p.reference, p.id_product, pl.name, ROUND(AVG(od.product_price / o.conversion_rate), 2) as avgPriceSold, 
			IFNULL((SELECT SUM(pa.quantity) FROM '._DB_PREFIX_.'product_attribute pa WHERE pa.id_product = p.id_product GROUP BY pa.id_product), p.quantity) as quantity,
			IFNULL(SUM(od.product_quantity), 0) AS totalQuantitySold,
			ROUND(IFNULL(IFNULL(SUM(od.product_quantity), 0) / (1 + LEAST(TO_DAYS('.$arrayDateBetween[1].'), TO_DAYS(NOW())) - GREATEST(TO_DAYS('.$arrayDateBetween[0].'), TO_DAYS(p.date_add))), 0), 2) as averageQuantitySold,
			ROUND(IFNULL(SUM((od.product_price * od.product_quantity) / o.conversion_rate), 0), 2) AS totalPriceSold,
			(
				SELECT IFNULL(SUM(pv.counter), 0)
				FROM '._DB_PREFIX_.'page pa
				LEFT JOIN '._DB_PREFIX_.'page_viewed pv ON pa.id_page = pv.id_page
				LEFT JOIN '._DB_PREFIX_.'date_range dr ON pv.id_date_range = dr.id_date_range
				WHERE pa.id_object = p.id_product AND pa.id_page_type = 1
				AND dr.time_start BETWEEN '.$dateBetween.'
				AND dr.time_end BETWEEN '.$dateBetween.'
			) AS totalPageViewed
		FROM '._DB_PREFIX_.'product p
		LEFT JOIN '._DB_PREFIX_.'product_lang pl ON (p.id_product = pl.id_product AND pl.id_lang = '.(int)($this->getLang()).')
		LEFT JOIN '._DB_PREFIX_.'order_detail od ON od.product_id = p.id_product
		LEFT JOIN '._DB_PREFIX_.'orders o ON od.id_order = o.id_order
		WHERE p.active = 1 AND o.valid = 1
		AND o.invoice_date BETWEEN '.$dateBetween.'
		GROUP BY od.product_id';

		if (Validate::IsName($this->_sort))
		{
			$this->_query .= ' ORDER BY `'.$this->_sort.'`';
			if (isset($this->_direction) AND Validate::IsSortDirection($this->_direction))
				$this->_query .= ' '.$this->_direction;
		}
		if (($this->_start === 0 OR Validate::IsUnsignedInt($this->_start)) AND Validate::IsUnsignedInt($this->_limit))
			$this->_query .= ' LIMIT '.$this->_start.', '.($this->_limit);
		$this->_values = Db::getInstance(_PS_USE_SQL_SLAVE_)->ExecuteS($this->_query);
		$this->_totalCount = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('SELECT FOUND_ROWS()');
	}
}
