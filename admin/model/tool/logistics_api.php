<?php
class ModelToolLogisticsApi extends Controller {

	public function request($method, $url, $data = array(), $header = array(), $response_code = false){
		$link = trim($this->config->get('module_logistics_api_api_url')) . $url;
		$header[] = 'Content-Type: application/json';
		$header[] = 'Accept: application/json';
		$header[] = 'Authorization: Basic '. base64_encode(trim($this->config->get('module_logistics_api_api_username')) . ':' . trim($this->config->get('module_logistics_api_api_password')));
		$this->debug("Request to shoprenter - $link - START : " . print_r($data, true));
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Shoprenter-AKV-Opencart-Module');
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
		$this->debug("Answer from shoprenter - $link - [$code]: " . print_r($response, true));
		return $response;
	}

	public function exportCategories(){
		$start = microtime(true);

		$category_ids = $this->getCategoryIds();

		$this->debug("Export categories started " . count($category_ids));
		$errors_count = 0;
		foreach ($category_ids as $category_id){
			if(!$this->exportCategory($category_id)){
				$errors_count ++;
			}
			if($errors_count > 2){
				$this->debug("We got a lot of errors by adding the categories, check up the log file please");
				return false;
			}
		}

		$this->debug("Export categories finished " . count($category_ids) . " with " . round(microtime(true) - $start, 4) . " sec.");
		return true;
	}

	public function exportCategory($category_id){
		$laguages_links = $this->config->get('module_logistics_api_laguages_links');

		$shoprenter_id = $this->getCategoryLinkShoprenterId($category_id);

		if($shoprenter_id){
			if(!$this->config->get('module_logistics_api_update_category')){
				$this->debug("This category is exist in links, category_id: " . $category_id . " - " . $shoprenter_id);
				return $shoprenter_id;
			}
		}

		foreach ($laguages_links as $lang_id => $shoprenter_lang_id){
			$category_data[$shoprenter_lang_id] = $this->getCategory($category_id, $lang_id);
		}

		$category_main_data = $this->array_first($category_data);

		$data = array(
			"picture" => $this->slugify($category_main_data["image"]),
			"sortOrder" => $category_main_data["sort_order"],
			"status" => $category_main_data["status"],
			"productsStatus" => "1",
			"groupCode" => null
		);

		foreach ($category_data as $shoprenter_lang_id => $category_data_item){
			$data['categoryDescriptions'][] = array(
				"name" => $category_data_item['name'],
				"description" => $category_data_item["description"],
				"metaKeywords" => $category_data_item["meta_keyword"],
				"metaDescription" => $category_data_item["meta_description"],
				"customTitle" => $category_data_item["meta_title"],
				"language" => array(
					"id" => $shoprenter_lang_id
				)
			);
		}

		$parentCategory = array();
		$centralCategory = array();

		if($category_main_data['parent_id'] != 0){
			$parent_shoprenter_id = $this->getCategoryLinkShoprenterId($category_main_data['parent_id']);

			if(!$parent_shoprenter_id){
				$parent_shoprenter_id = $this->exportCategory($category_main_data['parent_id']);
			}

			if($parent_shoprenter_id){
				$parentCategory = array(
					"id" => $parent_shoprenter_id
				);
			}
		}
		if(count($parentCategory)){
			$data["parentCategory"] = $parentCategory;
		}
		if(count($centralCategory)){
			$data["centralCategory"] = $centralCategory;
		}


		if($shoprenter_id){
			$this->debug("This category is exist in links, category_id: " . $category_id . " - " . $shoprenter_id);
			if($this->config->get('module_logistics_api_update_category')){
				$this->request("PUT","/categoryExtend/" . $shoprenter_id,$data);
			}
			return true;
		}

		$result = $this->request("POST","/categoryExtend",$data);

		if($result){
			// Will create links with categories in Shoprenter
			if(isset($result['id'])){
				$this->debug("We got Shoprenter category_id " . $category_id . " - " . $result['id']);
				$this->addCategoryLink($category_id, $result['id']);
				return $result['id'];
			}
		}
		return false;
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

	public function exportProductAttributes($product_id, $shoprenter_product_id = ''){

		$laguages_links = $this->config->get('module_logistics_api_laguages_links');

		$attribute_groups = $this->getProductAttributeData($product_id);
		if(!$attribute_groups){
			return true;
		}
		// this is function will to create the group for all groups in opencart to one in shoprenter - because in there used only one group
		$main_group_id = 0;
		if($this->config->get('module_logistics_api_attributes_common_group')){
			$main_group_id = $this->getFirstAttributeGroup();
		}

		foreach ($attribute_groups as $attribute_group){
			if($main_group_id){
				$attribute_group['attribute_group_id'] = $main_group_id;
				$attribute_group["name"] = "Main";
			}
			$product_attr_data = $this->getProductAttributes($product_id, $attribute_group['attribute_id']);
			$shoprenter_atr_group_id = $this->getAttributeGroupLinkShoprenterId($attribute_group['attribute_group_id']);

			if(!$shoprenter_atr_group_id){
				$data = array(
					"name" => $attribute_group["name"]
				);

				$result = $this->request("POST","/productClasses", $data);
				if($result){
					// Will create links with categories in Shoprenter
					if(isset($result['id'])){
						$this->debug("We got Shoprenter attribute_group_id " . $product_id . " - " . $attribute_group['attribute_group_id'] . " - " . $result['id']);
						$this->addAttributeGroupLink($attribute_group['attribute_group_id'], $result['id']);
						$shoprenter_atr_group_id = $result['id'];
					}
				}
			}

			$shoprenter_atr_id = $this->getAttributeShoprenterId($attribute_group['attribute_id']);

			if(!$shoprenter_atr_id){
				$data = array(
					"type" => "TEXT",
					"name" => $attribute_group['attribute_name'],
					"priority" => "NORMAL",
					"sortOrder" => $attribute_group['sortorder'],
					"required" => "0",
					"textFieldType" => "INPUT",
					"translateable" => "0"
				);

				$result = $this->request("POST","/textAttributes", $data);
				if($result){
					// Will create links with categories in Shoprenter
					if(isset($result['id'])){
						$this->debug("We got Shoprenter attribute_id " . $product_id . " - " . $attribute_group['attribute_id'] . " - " . $result['id']);
						$this->addAttributeLink($attribute_group['attribute_id'], $result['id']);
						$shoprenter_atr_id = $result['id'];

						// WE create here relation with group and attribute

						if($shoprenter_atr_group_id && $shoprenter_atr_id){
							$data = array(
								"productClass" => array(
									"id" => $shoprenter_atr_group_id
								),
								"attribute" => array(
									"id" => $shoprenter_atr_id
								)
							);
							$result = $this->request("POST","/productClassAttributeRelations", $data);
							if(isset($result['id'])) {
								$this->debug("We created relations with attribute group and attribute " . $product_id . " - " . $attribute_group['attribute_id'] . " - " . $attribute_group['attribute_group_id']);
							}
						}

					}
				}
			}

			if($shoprenter_atr_group_id && $shoprenter_atr_id){

				if($shoprenter_atr_id) {
					// we create attribute description
					foreach ($product_attr_data as $product_attr_item) {
						if (isset($laguages_links[$product_attr_item['language_id']])) {
							$data = array(
								"name" => $product_attr_item["name"],
								"description" => "",
								"attribute" => array(
									"id" => $shoprenter_atr_id
								),
								"language" => array(
									"id" => $laguages_links[$product_attr_item['language_id']]
								)
							);
							$this->request("POST", "/attributeDescriptions", $data);
						}
					}
				}

				if(!$shoprenter_product_id){
					$shoprenter_product_id = $this->getProductLinkShoprenterId($product_id);
				}

				if($shoprenter_product_id){
					$data = array(
						"productClass" => array(
							"id" => $shoprenter_atr_group_id
						)
					);
					$result = $this->request("PUT","/productExtend/" . $shoprenter_product_id, $data);
					if(isset($result['id'])) {
						$this->debug("We created relations with attribute group and product " . $product_id . " - " . $attribute_group['attribute_group_id']);
					}

					// we add the relation with attr value and product
					$data = array(
						"textAttribute" => array(
							"id" => $shoprenter_atr_id
						),
						"product" => array(
							"id" => $shoprenter_product_id
						)
					);

					$shoprenter_atr_value_id = "";
					$result = $this->request("POST","/textAttributeValues/", $data);
					if(isset($result['id'])) {
						$this->debug("We created attribute value in the product " . $product_id . " - " . $attribute_group['attribute_group_id']);
						$shoprenter_atr_value_id = $result['id'];
					}

					if($shoprenter_atr_value_id){



						foreach ($product_attr_data as $product_attr_item){
							if(isset($laguages_links[$product_attr_item['language_id']])){
								if($this->config->get('module_logistics_api_attributes_values_multi_language')){
									$lang_data = array(
										"id" => $laguages_links[$product_attr_item['language_id']]
									);
								}else{
									$lang_data = null;
								}
								$data = array(
									"name" => $product_attr_item['text'],
									"language" => $lang_data,
									"textAttributeValue" => array (
										"id" => $shoprenter_atr_value_id
									)
								);
								$result = $this->request("POST","/textAttributeValueDescriptions", $data);
								if(isset($result['id'])) {
									$this->debug("We created attribute value description in the product " . $product_id . " - " . $attribute_group['attribute_group_id'] . " - " . $product_attr_item['language_id']);
								}
							}
						}
					}
				}
			}else{
				$this->debug("We can`t add to product the attribute, check log please " . $product_id . " - " . $attribute_group['attribute_id'] . " - " . $attribute_group['attribute_group_id']);
			}
		}
	}

	public function exportProductSpecialDiscount($product_id, $shoprenter_product_id = ''){
		$this->load->model('customer/customer_group');
		$customer_groups = $this->model_customer_customer_group->getCustomerGroups();

		if(!$shoprenter_product_id){
			$shoprenter_product_id = $this->getProductLinkShoprenterId($product_id);
		}

		// the first step - we need to delete old specials prices
		$result = $this->request("GET","/products/" . $shoprenter_product_id);

		if(isset($result['productSpecials'])){
			$action_count = 0;
			foreach ($result['productSpecials'] as $shoprenter_product_special){
				$link = str_replace(trim($this->config->get('module_logistics_api_api_url')),"",$shoprenter_product_special);
				$result = $this->request("GET", $link);
				if(isset($result['items'])){
					foreach ($result['items'] as $item){
						$link = str_replace(trim($this->config->get('module_logistics_api_api_url')),"",$item['href']);
						$this->request("DELETE", $link);
						$action_count++;
					}
				}
			}
			$this->debug("Wa deleted " . $action_count . " actions for product" . $shoprenter_product_id);
		}

		$custom_group_links = array();
		foreach ($customer_groups as $customer_group){
			$shoprenter_customer_group_id = $this->getCustomerGroupShoprenterId($customer_group['customer_group_id']);

			if(!$shoprenter_customer_group_id){
				$data = array(
					"name" => $customer_group['name'],
					"percentDiscount" => "0",
					"percentDiscountSpecialPrices" => "0"
				);
				$result = $this->request("POST","/customerGroups", $data);
				if(isset($result['id'])) {
					$this->addCustomerGroupLink($customer_group['customer_group_id'], $result['id']);
					$this->debug("We created relations with customer group and product " . $customer_group['customer_group_id'] . " - " . $result['id']);
					$shoprenter_customer_group_id = $result['id'];
				}
			}
			if($shoprenter_customer_group_id){
				$custom_group_links[$customer_group['customer_group_id']] = $shoprenter_customer_group_id;
			}
		}

		$this->load->model('catalog/product');
		$product_specials = $this->model_catalog_product->getProductSpecials($product_id);
		if(count($product_specials)){
			foreach ($product_specials as $product_special){
				if(isset($custom_group_links[$product_special['customer_group_id']])) {
					$data = array(
						"priority" => $product_special['priority'],
						"price" => $product_special['price'],
						"dateFrom" => $product_special['date_start'],
						"dateTo" => $product_special['date_end'],
						"minQuantity" => "0",
						"maxQuantity" => "100",
						"product" => array(
							"id" => $shoprenter_product_id
						),
						"customerGroup" => array(
							"id" => $custom_group_links[$product_special['customer_group_id']]
						)
					);

					$result = $this->request("POST","/productSpecials", $data);
					if(isset($result['id'])) {
						$this->debug("We created special price to product " . $product_id . " - " . $product_special['customer_group_id'] . " - " . $product_special['price']);
					}
				}
			}
		}

		$product_discounts = $this->model_catalog_product->getProductDiscounts($product_id);
		if(count($product_discounts)){
			foreach ($product_discounts as $product_discount){
				if(isset($custom_group_links[$product_discount['customer_group_id']])) {
					$data = array(
						"priority" => $product_discount['priority'],
						"price" => $product_discount['price'],
						"dateFrom" => $product_discount['date_start'],
						"dateTo" => $product_discount['date_end'],
						"minQuantity" => $product_discount['quantity'],
						"maxQuantity" => "100",
						"product" => array(
							"id" => $shoprenter_product_id
						),
						"customerGroup" => array(
							"id" => $custom_group_links[$product_discount['customer_group_id']]
						)
					);

					$result = $this->request("POST","/productSpecials", $data);
					if(isset($result['id'])) {
						$this->debug("We created special price to product " . $product_id . " - " . $product_discount['customer_group_id'] . " - " . $product_discount['price']);
					}
				}
			}
		}
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

	public function getProductAttributeData($product_id){
		$query = $this->db->query("SELECT pa.*,agd.*,ad.name as 'attribute_name', oc.sort_order as 'sortorder' FROM " . DB_PREFIX . "product_attribute pa 
		LEFT JOIN " . DB_PREFIX . "attribute oc ON (oc.attribute_id = pa.attribute_id) 
		LEFT JOIN " . DB_PREFIX . "attribute_group_description agd ON (agd.attribute_group_id = oc.attribute_group_id )
		LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (ad.attribute_id = pa.attribute_id) 
		WHERE pa.product_id = '" . (int)$product_id . "' 
			AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "' 
			AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'
			AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "'
			ORDER BY pa.attribute_id");
		if($query->num_rows){
			return $query->rows;
		}
		return false;
	}

	public function getProductAttributes($product_id, $attribute_id){
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "product_attribute pa 
		LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (ad.attribute_id = pa.attribute_id) 
		WHERE pa.product_id = '" . (int)$product_id . "' AND pa.attribute_id='" . $attribute_id . "'");
		if($query->num_rows){
			return $query->rows;
		}
		return false;
	}

	public function getFirstAttributeGroup(){
		$query = $this->db->query("SELECT attribute_group_id FROM " . DB_PREFIX . "attribute_group ORDER BY attribute_group_id LIMIT 1");
		if($query->num_rows){
			return $query->row['attribute_group_id'];
		}
		return false;
	}

	public function getShoprenterLanguages(){
		$languages = array();
		$result = $this->request("GET","/languages");
		if(isset($result['items'])){
			foreach ($result['items'] as $lang_item){
				$lang_id = str_replace(trim($this->config->get('module_logistics_api_api_url')) . "/languages/","",$lang_item['href']);
				$result_data = $this->request("GET","/languages/" . $lang_id);
				if(isset($result_data['id'])){
					$languages[$result_data['id']] = $result_data['name'];
				}
			}
		}
		return  $languages;
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