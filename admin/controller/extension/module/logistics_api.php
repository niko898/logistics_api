<?php
class ControllerExtensionModuleLogisticsApi extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/logistics_api');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		$this->load->model('extension/module/logistics_api');
		$this->model_extension_module_logistics_api->upgrade();

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('module_logistics_api', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/account', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/module/logistics_api', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$data['delete_all_links'] = $this->url->link('tool/logistics_api/deleteAllLinks', 'user_token=' . $this->session->data['user_token'], true);

		$data["logistics_api_cron"] = "php " . realpath(DIR_SYSTEM . "../cron/logistics_api.php");

		if (isset($this->request->post['module_logistics_api_status'])) {
			$data['status'] = $this->request->post['module_logistics_api_status'];
		} else {
			$data['status'] = $this->config->get('module_logistics_api_status');
		}

		if (isset($this->request->post['module_logistics_api_debug'])) {
			$data['debug'] = $this->request->post['module_logistics_api_debug'];
		} else {
			$data['debug'] = $this->config->get('module_logistics_api_debug');
		}

		if (isset($this->error['module_logistics_api_api_username'])) {
			$data['error_api_username'] = $this->error['module_logistics_api_api_username'];
		} else {
			$data['error_api_username'] = '';
		}

		if (isset($this->error['module_logistics_api_api_password'])) {
			$data['error_api_password'] = $this->error['module_logistics_api_api_password'];
		} else {
			$data['error_api_password'] = '';
		}

		

		if (isset($this->request->post['module_logistics_api_api_username'])) {
			$data['api_username'] = $this->request->post['module_logistics_api_api_username'];
		} else {
			$data['api_username'] = $this->config->get('module_logistics_api_api_username');
		}

		if (isset($this->request->post['module_logistics_api_api_password'])) {
			$data['api_password'] = $this->request->post['module_logistics_api_api_password'];
		} else {
			$data['api_password'] = $this->config->get('module_logistics_api_api_password');
		}

		if (isset($this->request->post['module_logistics_api_sandbox_status'])) {
			$data['sandbox_status'] = $this->request->post['module_logistics_api_sandbox_status'];
		} else {
			$data['sandbox_status'] = $this->config->get('module_logistics_api_sandbox_status');
		}

		if (isset($this->request->post['module_logistics_api_update_product'])) {
			$data['update_product'] = $this->request->post['module_logistics_api_update_product'];
		} else {
			$data['update_product'] = $this->config->get('module_logistics_api_update_product');
		}

		if (isset($this->request->post['module_logistics_api_update_images'])) {
			$data['update_images'] = $this->request->post['module_logistics_api_update_images'];
		} else {
			$data['update_images'] = $this->config->get('module_logistics_api_update_images');
		}


		$this->load->model('localisation/language');

		$data['languages'] = $this->model_localisation_language->getLanguages();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/logistics_api', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/logistics_api')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['module_logistics_api_api_username']) {
			$this->error['logistics_api_api_username'] = $this->language->get('error_api_username');
		}

		if (!$this->request->post['module_logistics_api_api_password']) {
			$this->error['logistics_api_api_password'] = $this->language->get('error_api_password');
		}

		if (!$this->request->post['module_logistics_api_api_url']) {
			$this->error['logistics_api_api_url'] = $this->language->get('error_api_url');
		}


		return !$this->error;
	}

	public function install(){
		$this->load->model('extension/module/logistics_api');
		$this->model_extension_module_logistics_api->install();
	}

	public function uninstall(){
		$this->load->model('extension/module/logistics_api');
		$this->model_extension_module_logistics_api->uninstall();
	}
}