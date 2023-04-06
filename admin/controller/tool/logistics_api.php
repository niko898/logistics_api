<?php
class ControllerToolLogisticsApi extends Controller {

	public function getProducts(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->request("GET", "/products?full=1");
	}

	public function createCategory(){
		$category_id = 17;
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->exportCategory($category_id);
	}

	public function createProduct(){
		$product_id = 40;
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->exportProduct($product_id);
	}

	public function exportCategories(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->exportCategories();
	}

	public function syncAttributeGroups(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->exportProductAttributes(40);
	}

	public function exportProductSpecial(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->exportProductSpecialDiscount(40);
	}

	public function updateProductImages(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->updateProductImages(40, "catalog/demo/iphone_1.jpg", "cHJvZHVjdC1wcm9kdWN0X2lkPTU2MQ==");
	}

	public function mainExport(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->mainExport();
	}

	public function deleteAllLinks(){
		$this->load->model('tool/logistics_api');
		$this->model_tool_logistics_api->deleteAllLinks();
	}
}