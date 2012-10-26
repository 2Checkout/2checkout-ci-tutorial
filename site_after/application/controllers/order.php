<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Order extends CI_Controller {

	function __construct()
	{
		parent::__construct();

		$this->load->database();

		$this->form_validation->set_error_delimiters($this->config->item('error_start_delimiter', 'ion_auth'), $this->config->item('error_end_delimiter', 'ion_auth'));

		require_once(APPPATH . 'libraries/Twocheckout.php');
	}

	public function register()
	{
		//validate form input
		$this->form_validation->set_rules('first_name', 'First Name', 'required|xss_clean');
		$this->form_validation->set_rules('last_name', 'Last Name', 'required|xss_clean');
		$this->form_validation->set_rules('email', 'Email Address', 'required|valid_email');
		$this->form_validation->set_rules('password', 'Password', 'required|min_length[' . $this->config->item('min_password_length', 'ion_auth') . ']|max_length[' . $this->config->item('max_password_length', 'ion_auth') . ']|matches[password_confirm]');
		$this->form_validation->set_rules('password_confirm', 'Password Confirmation', 'required');

		if ($this->form_validation->run() == true)
		{
			$username = strtolower($this->input->post('first_name')) . ' ' . strtolower($this->input->post('last_name'));
			$email    = $this->input->post('email');
			$password = $this->input->post('password');

			$additional_data = array(
				'first_name' => $this->input->post('first_name'),
				'last_name'  => $this->input->post('last_name'),
				'last_billed' => time()

			);
		}
		if ($this->form_validation->run() == true && $this->ion_auth->register($username, $password, $email, $additional_data))
		{ 
			//check to see if we are creating the user
			$this->session->set_flashdata('message', $this->ion_auth->messages());

			//get user id
			$query = $this->db->query("SELECT * FROM users WHERE email = '$email'");
			$row = $query->row();
			$id = $row->id; 

			$args = array(
			    'sid' => 1817037,
			    'mode' => "2CO",
			    'li_0_name' => "Monthly Subscription",
			    'li_0_price' => "1.00",
			    'li_0_recurrence' => "1 Month",
			    'merchant_order_id' => $id
			);
			Twocheckout_Charge::redirect($args);

		}
		else
		{ 
			//display the create user form
			//set the flash data error message if there is one
			$this->data['message'] = (validation_errors() ? validation_errors() : ($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));

			$this->data['first_name'] = array(
				'name'  => 'first_name',
				'id'    => 'first_name',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('first_name'),
			);
			$this->data['last_name'] = array(
				'name'  => 'last_name',
				'id'    => 'last_name',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('last_name'),
			);
			$this->data['email'] = array(
				'name'  => 'email',
				'id'    => 'email',
				'type'  => 'text',
				'value' => $this->form_validation->set_value('email'),
			);
			$this->data['password'] = array(
				'name'  => 'password',
				'id'    => 'password',
				'type'  => 'password',
				'value' => $this->form_validation->set_value('password'),
			);
			$this->data['password_confirm'] = array(
				'name'  => 'password_confirm',
				'id'    => 'password_confirm',
				'type'  => 'password',
				'value' => $this->form_validation->set_value('password_confirm'),
			);
		}

	  	$this->load->view('/include/header');
	  	$this->load->view('/include/navblank');
	  	$this->load->view('/order/register', $this->data);
        $this->load->view('/include/footer');
	}

	public function passback()
	{
		$params = array();
		foreach ($_REQUEST as $k => $v) {
		    $params[$k] = $v;
		}

		$passback = Twocheckout_Return::check($params, "tango", 'array');

		if ($passback['code'] == 'Success') {
			$id = $params['merchant_order_id'];
			$order_number = $params['order_number'];
			$invoice_id = $params['invoice_id'];
			$data = array(
				'active' => 1,
				'order_number' => $order_number,
				'last_invoice' => $invoice_id
				);
			$this->ion_auth->update($id, $data);

			$this->load->view('/include/header');
		  	$this->load->view('/include/navblank');
		  	$this->load->view('/order/return_success');
	        $this->load->view('/include/footer');
    	} else {
    		$this->load->view('/include/header');
		  	$this->load->view('/include/navblank');
		  	$this->load->view('/order/return_failed');
	        $this->load->view('/include/footer');
	    }
	}

	public function notification()
	{
		$params = array();
		foreach ($_POST as $k => $v) {
		    $params[$k] = $v;
		}

		$message = Twocheckout_Notification::check($params, "tango", 'array');

		if ($message['code'] == 'Success') {

			switch ($params['message_type']) {
				case 'FRAUD_STATUS_CHANGED':
					if ($params['fraud_status'] == 'fail') {
						$id = $params['vendor_order_id'];
						$data = array(
							'active' => 0
							);
						$this->ion_auth->update($id, $data);
					}
					break;
				case 'RECURRING_INSTALLMENT_FAILED':
					$id = $params['vendor_order_id'];
					$data = array(
						'active' => 0
						);
					$this->ion_auth->update($id, $data);
					break;
				case 'RECURRING_INSTALLMENT_SUCCESS':
					$id = $params['vendor_order_id'];
				    $user = $this->ion_auth->user($id)->row();
					$status = $user->active;
						$data = array(
							'active' => 1,
							'last_billed' => time(),
							'last_invoice' => $params['invoice_id']
							);
						$this->ion_auth->update($id, $data);
					break;
			}
		}
	}

	public function cancel()
	{
		if ($this->input->post('cancel')) {
		    $user = $this->ion_auth->user()->row();
			$order_number = $user->order_number;
			$invoice_id = $user->last_invoice;
			
			//Define API User and Password
			Twocheckout::setCredentials("APIuser1817037", "APIpass1817037");

			//Stop recurring billing
			$args = array('sale_id' => $order_number);
			Twocheckout_Sale::stop($args, 'array');


			$last_bill_date = $user->last_billed;
			$next_bill_date = strtotime('+1 month',$last_bill_date);
			$remaining_days = floor(abs(time() - $next_bill_date)/60/60/24);
			$refund_amount = round((1.00 / 30) * $remaining_days, 2);

			//Refund remaining balance
			$args = array(
				'invoice_id' => $invoice_id,
				'category' => 5,
				'amount' => $refund_amount,
				'currency' => 'vendor',
				'comment' => 'Refunding remaining balance'
				);
			Twocheckout_Sale::refund($args, 'array');

			//Deactivate User
			$id = $user->id;
			$data = array(
				'active' => 0
				);
			$this->ion_auth->update($id, $data);
			$this->ion_auth->logout();

			//Reinit $data array for view
			$data = array(
               'remaining_days' => $remaining_days,
               'refund_amount' => $refund_amount
          	);

			$this->load->view('/include/header');
		  	$this->load->view('/include/navblank');
		  	$this->load->view('/order/cancel_success', $data);
		    $this->load->view('/include/footer');
		 } else {
			$this->load->view('/include/header');
		  	$this->load->view('/include/navblank');
		  	$this->load->view('/order/cancel');
		    $this->load->view('/include/footer');
		}
	}

	public function update()
	{
	    $user = $this->ion_auth->user()->row();
		$order_number = $user->order_number;
		redirect('https://www.2checkout.com/va/sales/customer/change_billing_method?sale_id='.$order_number, 'refresh');
	}

}

/* End of file order.php */
/* Location: ./application/controllers/order.php */