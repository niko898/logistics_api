<?php
class ModelToolLogisticsApi extends Controller {

	public function request($method, $url, $data = array(), $header = array(), $response_code = false){
		$link = "https://app.webaruhazlogisztika.eu/api/v1/";
		if(){
			$link = "https://sandbox.webaruhazlogisztika.eu/api/v1/";
		}
		
		$link .= $url;

		$header[] = 'Content-Type: application/json';
		$header[] = 'Accept: application/json';
		$header[] = 'Authorization: Basic '. base64_encode(trim($this->config->get('module_logistics_api_api_username')) . ':' . trim($this->config->get('module_logistics_api_api_password')));
		$this->debug("Request to logistics - $link - START : " . print_r($data, true));
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'XXLlogistics-AKV-Opencart-Module');
		curl_setopt($curl, CURLOPT_URL, $link);
		curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
		curl_setopt($curl, CURLOPT_HEADER, true);
		curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);
		$out = curl_exec($curl);
		$code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$header_size = curl_getinfo($curl,CURLINFO_HEADER_SIZE);
		curl_close($curl);
		$code = (int) $code;
		$errors = array(
			400 => 'Bad request',
			401 => 'Unauthorized',
			403 => 'Forbidden',
			404 => 'Not found',
			500 => 'Internal server error',
			502 => 'Bad gateway',
			503 => 'Service unavailable',
		);
		if ($code < 200 && $code > 204) {
			$this->debug("We got some error $code: " . $errors[$code]);
			return false;
		}

		if($response_code){
			return $code;
		}

		$result['header'] = substr($out, 0, $header_size);
		$result['body'] = substr( $out, $header_size );

		//$response['header'] = $result['header'];
		$response = json_decode($result['body'], true);
		usleep(300000);
		$this->debug("Answer from logistics - $link - [$code]: " . print_r($response, true));
		return $response;
	}


	public function mainExport(){
		$this->exportCategories();
		$this->exportProducts();

	}

	public function exportProducts(){
		$start = microtime(true);

		$product_ids = $this->getProductIds();

		$this->debug("Export products started " . count($product_ids));
		$errors_count = 0;
		foreach ($product_ids as $product_id){
			if(!$this->exportProduct($product_id)){
				$errors_count ++;
			}
			if($errors_count > 2){
				$this->debug("We got a lot of errors by adding the products, check up the log file please");
				return false;
			}
		}

		$this->debug("Export products finished " . count($product_ids) . " with " . round(microtime(true) - $start, 4) . " sec.");
		return true;
	}

	public function exportProduct($product_id){
		$laguages_links = $this->config->get('module_logistics_api_laguages_links');

		$product_data = $this->getProduct($product_id);
		if(!$product_data){
			return false;
		}

		$shoprenter_id = $this->getProductLinkShoprenterId($product_id);
		if($shoprenter_id) {
			if (!$this->config->get('module_logistics_api_update_product')) {
				$this->debug("This product is exist in links, product_id: " . $product_id . " - " . $shoprenter_id);
				return $shoprenter_id;
			}
		}

		$this->load->model('catalog/product');
		$product_data_desc = $this->model_catalog_product->getProductDescriptions($product_id);
		$product_data_categories = $this->model_catalog_product->getProductCategories($product_id);

		if($product_data['sku'] != ""){
			$product_code = $product_data['sku'];
		}elseif($product_data['model'] != ""){
			$product_code = $product_data['model'];
		}else{
			$product_code = $product_data['product_id'];
		}

		$data = array(
			"sku" => $product_code,
			"price" => $product_data['price'],
			"stock1" => $product_data['quantity'],
			"mainPicture" => $this->slugify($product_data['image']),
			"status" => $product_data['status'],
			"minimalOrderNumber" => $product_data['minimum'],
			"sortOrder" => $product_data['sort_order'],
		);

		foreach ($product_data_desc as $lang_id => $product_data_item){
			if(isset($laguages_links[$lang_id])){
				$data['productDescriptions'][] = array(
					"name" => $product_data_item['name'],
					"description" => $product_data_item['description'],
					"metaDescription" => $product_data_item['meta_description'],
					"metaTitle" => $product_data_item['meta_title'],
					"metaKeywords" => $product_data_item['meta_keyword'],
					"language" => array(
						"id" => $laguages_links[$lang_id]
					)
				);
			}
		}

		if(count($product_data_categories)){
			foreach ($product_data_categories as $category_id){
				$shoprenter_category_id = $this->getCategoryLinkShoprenterId($category_id);
				if($shoprenter_category_id){
					$data['productCategoryRelations'][] = array(
						"category" => array(
							"id" => $shoprenter_category_id,
						)
					);
				}
			}
		}

		if($shoprenter_id){
			$this->debug("This product is exist in links, product_id: " . $product_id . " - " . $shoprenter_id);
			if($this->config->get('module_logistics_api_update_product')){
				$this->request("PUT","/productExtend/" . $shoprenter_id, $data);
			}
			return true;
		}

		$result = $this->request("POST","/productExtend",$data);
		if($result){
			// Will create links with categories in Shoprenter
			if(isset($result['id'])){
				$this->debug("We got Shoprenter product_id " . $product_id . " - " . $result['id']);
				$this->addProductLink($product_id, $result['id']);

				if($this->config->get('module_logistics_api_update_product_attributes')){
					$this->exportProductAttributes($product_id,$result['id']);
				}

				if($this->config->get('module_logistics_api_update_special_discount')) {
					$this->exportProductSpecialDiscount($product_id, $result['id']);
				}

				if($this->config->get('module_logistics_api_update_images')) {
					$this->updateProductImages($product_id, $product_data['image'], $result['id']);
				}


				return $result['id'];
			}
		}
		return false;
	}


	public function updateProductImages($product_id, $main_image, $shoprenter_product_id = ''){
		if(!$shoprenter_product_id){
			$shoprenter_product_id = $this->getProductLinkShoprenterId($product_id);
		}

		if(!$main_image){
			$product_main_image = $this->getProductImage($product_id);
		}else{
			$product_main_image = $main_image;
		}

		$product_additional_images = $this->getProductImages($product_id);
		$result = $this->request("GET","/products/" . $shoprenter_product_id);

		if(isset($result['allImages'])) {
			foreach ($result['allImages'] as $shoprenter_image_type => $shoprenter_image_path) {
				if($shoprenter_image_type == "mainImage"){
					if($this->request("HEAD", $shoprenter_image_path, array(), array(),true) != 200){
						$this->uploadImageData($product_main_image);
					}else{
						$this->debug("The main image dont changed " . $product_id);
					}
				}
			}
		}

		$result_images = $this->request("GET","/productImages?productId=" . $shoprenter_product_id);

		if(isset($result_images['items']) && count($result_images['items'])){
			// here we got all additional images and will delete all of them, this is not the best way, but in shoprenter we cant get fast checking existence
			foreach ($result_images['items'] as $image_item){
				$link = str_replace(trim($this->config->get('module_logistics_api_api_url')),"", $image_item['href']);
				$this->request("DELETE", $link);
			}

			foreach ($product_additional_images['image_paths'] as $product_additional_image){
				$this->uploadImageData($product_additional_image);
				$data = array(
					"imagePath" => $product_additional_image,
					"sortOrder" => $product_additional_images['sort'][$product_additional_image],
					"product" => array(
						"id" => $shoprenter_product_id
					)
				);
				$result = $this->request("POST","/productImages", $data);
				if(isset($result['id'])) {
					$this->debug("We assign the additional image to product " . $product_id . " - " . $product_additional_image);
				}
			}
		}else{
			if(isset($product_additional_images['image_paths']) && count($product_additional_images['image_paths'])){
				foreach ($product_additional_images['image_paths'] as $product_additional_image){
					$this->uploadImageData($product_additional_image);
					$data = array(
						"imagePath" => $product_additional_image,
						"sortOrder" => $product_additional_images['sort'][$product_additional_image],
						"product" => array(
							"id" => $shoprenter_product_id
						)
					);
					$result = $this->request("POST","/productImages", $data);
					if(isset($result['id'])) {
						$this->debug("We assign the image to product " . $product_id . " - " . $product_additional_image);
					}
				}
			}
		}
	}

	public function getProductImage($product_id){
		$query = $this->db->query("SELECT image FROM " . DB_PREFIX . "product WHERE product_id=" . (int)$product_id . " LIMIT 1");
		if ($query->num_rows) {
			return $this->slugify($query->row['image']);
		}
		return false;
	}

	public function getProductImages($product_id) {
		$query = $this->db->query("SELECT image, sort_order FROM " . DB_PREFIX . "product_image WHERE product_id = '" . (int)$product_id . "' ORDER BY sort_order ASC");

		$images = array();

		if($query->num_rows){
			foreach ($query->rows as $row){
				$sug_image = $this->slugify($row['image']);
				$images['image_paths'][] = $sug_image;
				$images['sort'][$sug_image] = $row['sort_order'];
			}
		}
		return $images;
	}

	public function uploadImageData($image_path){
		$image = $this->slugify($image_path);
		$data = array(
			"filePath" => $image,
			"type" => "image",
			"attachment" => base64_encode(file_get_contents(DIR_IMAGE . $image_path))
		);
		$result = $this->request("POST","/files", $data);
		if(isset($result['id'])) {
			$this->debug("The image was upload " . $image);
		}
	}

	public function getAllImages()
	{
		$all_images = array();
		$query = $this->db->query("SELECT image FROM " . DB_PREFIX . "product ");
		if ($query->num_rows) {
			foreach ($query->rows as $row) {
				$all_images[] = $row['image'];
			}
		}
		$query = $this->db->query("SELECT image FROM " . DB_PREFIX . "product_image ");
		if ($query->num_rows) {
			foreach ($query->rows as $row) {
				$all_images[] = $row['image'];
			}
		}
		$query = $this->db->query("SELECT image FROM " . DB_PREFIX . "category ");
		if ($query->num_rows) {
			foreach ($query->rows as $row) {
				$all_images[] = $row['image'];
			}
		}

		return $all_images;
	}

	

	public function addCategoryLink($category_id, $shoprenter_category_id){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "shoprenter_category_links` WHERE category_id = '" . (int)$category_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "shoprenter_category_links` SET category_id = '" . (int)$category_id . "', `shoprenter_category_id` = '" . $shoprenter_category_id . "'");
	}

	public function addProductLink($product_id, $shoprenter_product_id){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "shoprenter_product_links` WHERE product_id = '" . (int)$product_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "shoprenter_product_links` SET product_id = '" . (int)$product_id . "', `shoprenter_product_id` = '" . $shoprenter_product_id . "'");
	}

	public function addAttributeGroupLink($attribute_group_id, $shoprenter_attribute_group_id){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "shoprenter_attribute_group_links` WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "shoprenter_attribute_group_links` SET attribute_group_id = '" . (int)$attribute_group_id . "', `shoprenter_attribute_group_id` = '" . $shoprenter_attribute_group_id . "'");
	}

	public function addAttributeLink($attribute_id, $shoprenter_attribute_id){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "shoprenter_attribute_links` WHERE attribute_id = '" . (int)$attribute_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "shoprenter_attribute_links` SET attribute_id = '" . (int)$attribute_id . "', `shoprenter_attribute_id` = '" . $shoprenter_attribute_id . "'");
	}

	public function addCustomerGroupLink($customer_group_id, $shoprenter_customer_group_id){
		$this->db->query("DELETE FROM `" . DB_PREFIX . "shoprenter_customer_group_links` WHERE customer_group_id = '" . (int)$customer_group_id . "'");
		$this->db->query("INSERT INTO `" . DB_PREFIX . "shoprenter_customer_group_links` SET customer_group_id = '" . (int)$customer_group_id . "', `shoprenter_customer_group_id` = '" . $shoprenter_customer_group_id . "'");
	}

	public function getCategoryIds(){
		$query = $this->db->query("SELECT `category_id` FROM `" . DB_PREFIX . "category` ORDER BY `parent_id` ASC");

		$categories = array();

		if($query->num_rows){
			foreach ($query->rows as $row){
				$categories[] = $row['category_id'];
			}
		}
		return $categories;
	}

	public function getProductIds(){
		$query = $this->db->query("SELECT `product_id` FROM `" . DB_PREFIX . "product` ORDER BY `status` DESC");

		$products = array();

		if($query->num_rows){
			foreach ($query->rows as $row){
				$products[] = $row['product_id'];
			}
		}
		return $products;
	}

	public function getCategoryLinkShoprenterId($category_id){
		$query = $this->db->query("SELECT `shoprenter_category_id` FROM `" . DB_PREFIX . "shoprenter_category_links` WHERE category_id = '" . (int)$category_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['shoprenter_category_id'];
		}
		return false;
	}

	public function getProductLinkShoprenterId($product_id){
		$query = $this->db->query("SELECT `shoprenter_product_id` FROM `" . DB_PREFIX . "shoprenter_product_links` WHERE product_id = '" . (int)$product_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['shoprenter_product_id'];
		}
		return false;
	}

	public function getAttributeGroupLinkShoprenterId($attribute_group_id){
		$query = $this->db->query("SELECT `shoprenter_attribute_group_id` FROM `" . DB_PREFIX . "shoprenter_attribute_group_links` WHERE attribute_group_id = '" . (int)$attribute_group_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['shoprenter_attribute_group_id'];
		}
		return false;
	}

	public function getAttributeShoprenterId($attribute_id){
		$query = $this->db->query("SELECT `shoprenter_attribute_id` FROM `" . DB_PREFIX . "shoprenter_attribute_links` WHERE attribute_id = '" . (int)$attribute_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['shoprenter_attribute_id'];
		}
		return false;
	}

	public function getCustomerGroupShoprenterId($customer_group_id){
		$query = $this->db->query("SELECT `shoprenter_customer_group_id` FROM `" . DB_PREFIX . "shoprenter_customer_group_links` WHERE customer_group_id = '" . (int)$customer_group_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['shoprenter_customer_group_id'];
		}
		return false;
	}

	public function getCategoryLinkCategoryId($shoprenter_category_id){
		$query = $this->db->query("SELECT `shoprenter_category_id` FROM `" . DB_PREFIX . "shoprenter_category_links` WHERE shoprenter_category_id = '" . $shoprenter_category_id . "' LIMIT 1");
		if($query->num_rows){
			return $query->row['category_id'];
		}
		return false;
	}

	public function getCategory($category_id, $language_id) {
		$query = $this->db->query("SELECT DISTINCT *, (SELECT GROUP_CONCAT(cd1.name ORDER BY level SEPARATOR '&nbsp;&nbsp;&gt;&nbsp;&nbsp;') FROM " . DB_PREFIX . "category_path cp LEFT JOIN " . DB_PREFIX . "category_description cd1 ON (cp.path_id = cd1.category_id AND cp.category_id != cp.path_id) WHERE cp.category_id = c.category_id AND cd1.language_id = '" . (int)$language_id . "' GROUP BY cp.category_id) AS path FROM " . DB_PREFIX . "category c LEFT JOIN " . DB_PREFIX . "category_description cd2 ON (c.category_id = cd2.category_id) WHERE c.category_id = '" . (int)$category_id . "' AND cd2.language_id = '" . (int)$language_id . "'");

		return $query->row;
	}

	public function getProduct($product_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$product_id . "' ");

		return $query->row;
	}

	public function debug($text) {
		if ($this->config->get('module_logistics_api_debug')) {
			$this->log->write("Shoprenter Integration: " . $text);
		}
	}

	private function array_first($array, $default = null)
	{
		foreach ($array as $key => $item) {
			return $item;
		}
		return $default;
	}

	private function slugify($string) {
		$converter = array(
			'а' => 'a',    'б' => 'b',    'в' => 'v',    'г' => 'g',    'д' => 'd',
			'е' => 'e',    'ё' => 'e',    'ж' => 'zh',   'з' => 'z',    'и' => 'i',
			'й' => 'y',    'к' => 'k',    'л' => 'l',    'м' => 'm',    'н' => 'n',
			'о' => 'o',    'п' => 'p',    'р' => 'r',    'с' => 's',    'т' => 't',
			'у' => 'u',    'ф' => 'f',    'х' => 'h',    'ц' => 'c',    'ч' => 'ch',
			'ш' => 'sh',   'щ' => 'sch',  'ь' => '',     'ы' => 'y',    'ъ' => '',
			'э' => 'e',    'ю' => 'yu',   'я' => 'ya',

			'А' => 'A',    'Б' => 'B',    'В' => 'V',    'Г' => 'G',    'Д' => 'D',
			'Е' => 'E',    'Ё' => 'E',    'Ж' => 'Zh',   'З' => 'Z',    'И' => 'I',
			'Й' => 'Y',    'К' => 'K',    'Л' => 'L',    'М' => 'M',    'Н' => 'N',
			'О' => 'O',    'П' => 'P',    'Р' => 'R',    'С' => 'S',    'Т' => 'T',
			'У' => 'U',    'Ф' => 'F',    'Х' => 'H',    'Ц' => 'C',    'Ч' => 'Ch',
			'Ш' => 'Sh',   'Щ' => 'Sch',  'Ь' => '',     'Ы' => 'Y',    'Ъ' => '',
			'Э' => 'E',    'Ю' => 'Yu',   'Я' => 'Ya',
		);

		$string = strtr($string, $converter);
		$string = preg_replace('/[-\s]+/', '-', $string);
		return $string;
	}

	public function deleteAllLinks(){
		$this->db->query("TRUNCATE `" . DB_PREFIX . "shoprenter_attribute_group_links`;");
		$this->db->query("TRUNCATE `" . DB_PREFIX . "shoprenter_attribute_links`;");
		$this->db->query("TRUNCATE `" . DB_PREFIX . "shoprenter_category_links`;");
		$this->db->query("TRUNCATE `" . DB_PREFIX . "shoprenter_customer_group_links`;");
		$this->db->query("TRUNCATE `" . DB_PREFIX . "shoprenter_product_links`;");
	}

}