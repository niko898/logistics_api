<?php
class ModelExtensionModuleLogisticsApi extends Model {

	public function install()
	{
		$this->installTables();

		// default settings
		$this->load->model('setting/setting');

		$this->model_setting_setting->editSetting('module_logistics_api', array(
			'module_logistics_api_status' => 0,
			'module_logistics_api_debug' => 0,
			'module_logistics_api_api_username' => '',
			'module_logistics_api_api_password' => '',
			'module_logistics_api_api_url' => 'http://akvtest.api.myshoprenter.hu'
		));
		return TRUE;
	}

	public function installTables()
	{
		$this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "shoprenter_category_links` (
			`category_link_id` int(11) NOT NULL AUTO_INCREMENT,
			`category_id` int(11) NOT NULL,
			`shoprenter_category_id` varchar(60) NOT NULL,
			PRIMARY KEY (`category_link_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		$this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "shoprenter_product_links` (
			`product_link_id` int(11) NOT NULL AUTO_INCREMENT,
			`product_id` int(11) NOT NULL,
			`shoprenter_product_id` varchar(60) NOT NULL,
			PRIMARY KEY (`product_link_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");

		$this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "shoprenter_attribute_group_links` (
			`attribute_group_link_id` int(11) NOT NULL AUTO_INCREMENT,
			`attribute_group_id` int(11) NOT NULL,
			`shoprenter_attribute_group_id` varchar(60) NOT NULL,
			PRIMARY KEY (`attribute_group_link_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		$this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "shoprenter_attribute_links` (
			`attribute_link_id` int(11) NOT NULL AUTO_INCREMENT,
			`attribute_id` int(11) NOT NULL,
			`shoprenter_attribute_id` varchar(60) NOT NULL,
			PRIMARY KEY (`attribute_link_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
		$this->db->query("CREATE TABLE IF NOT EXISTS`" . DB_PREFIX . "shoprenter_customer_group_links` (
			`customer_group_link_id` int(11) NOT NULL AUTO_INCREMENT,
			`customer_group_id` int(11) NOT NULL,
			`shoprenter_customer_group_id` varchar(60) NOT NULL,
			PRIMARY KEY (`customer_group_link_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}

	public function upgrade()
	{
		$this->installTables();
	}

	public function uninstall()
	{
		return TRUE;
	}

}