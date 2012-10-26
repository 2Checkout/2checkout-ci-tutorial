<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Frontpage extends CI_Controller {

   public function index()
	{
      $this->load->view('include/header');
	  if (!$this->ion_auth->logged_in())
	  {
		$this->load->view('include/navlogin');
	  }
	  else
	  {
	  	$this->load->view('include/navbar');
	  }
      $this->load->view('frontpage');
      $this->load->view('include/footer');
	}

	public function content()
	{
	  if (!$this->ion_auth->logged_in())
	  {
			//redirect them to the login page
			redirect('auth/login', 'refresh');
	  }
	  else
	  {
	  	$this->load->view('include/header');
	  	$this->load->view('include/navbar');
	  	$this->load->view('content');
        $this->load->view('include/footer');
	  }
	}

}

/* End of file frontpage.php */
/* Location: ./application/controllers/frontpage.php */
