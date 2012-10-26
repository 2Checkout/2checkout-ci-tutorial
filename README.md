## 2Checkout CodeIgniter Integration Tutorial
----------------------------------------

In this tutorial we will walk through integrating the 2Checkout payment method into an existing user management application built on CodeIgniter Bootstrap (CodeIgniter 2.1.2, TwitterBootstrap 2.1.0). This demo application also utilizes the ion_auth user authentication library. The source for the example application used in this tutorial can be accessed in this Github repository.

Setting up the Example Application
----------------------------------

We need an existing example application to demonstrate the integration so lets download or clone the 2checkout-ci-example application.

```shell
$ git clone https://github.com/2checkout/2checkout-ci-tutorial.git
```

This repository contains both an example before and after application so that we can follow along with the tutorial using the site\_before app and compare the result with the site\_after app. We can start by moving the site\_before directory to the webroot directory on our server and let rename it to web-widgets which is the name of our site.

Now we need to create our database.

```shell
create database webwidgets;
```

Next we run the mysql_database.sql file on our database.

```shell
mysql webwidgets < mysql_database.sql -u <mysql username> -p
```

Now we can specify our database credentials in our application.

application/config/database.php

```php
$db['default']['hostname'] = 'localhost';
$db['default']['username'] = 'your mysql username';
$db['default']['password'] = 'your mysql password';
$db['default']['database'] = 'webwidgets';
$db['default']['dbdriver'] = 'mysql';
```

We can then view the example application at [http://localhost/web-widgets/index.php](http://localhost/web-widgets/index.php).

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-1.png)

You can see that this site requires users to signup for a membership to access the web-widget content pages. Once logged in, they can also manage their user account, but do not have access to the admin functions.

In this tutorial, we will integrate the 2Checkout payment method so that the user must order a recurring monthly membership before gaining access to the contect. We will also setup a listener that will be responsible for updating the users access to the contact based on the Notifications that 2Checkout sends on their recurring billing status and provide the user with the ability to update their billing information and cancel their recurring membership.

Setting up the 2Checkout PHP Library
------------------------------------
2Checkouts a PHP library provides us with a simple bindings to the API, INS and Checkout process so that we can integrate each feature with only a few lines of code. We can download or clone the library at [https://github.com/2checkout/2checkout-php](https://github.com/2checkout/2checkout-php).

Including the library is as easy as copying contents of the **lib** directory to "application/libraries" and then we can require the library where we want it in our application. In our case, we will be working with the **order** controller so in "application/contollers/order.php" we will require the Twocheckout.php abstract class in the constructor.

```php
<?php

require_once(APPPATH . 'libraries/Twocheckout.php');
```

Know we have access to all of the 2Checkout bindings and are ready to integrate.

Checkout
--------
Currently we are activating users automatically. So lets go ahead and turn automatic activation off in the ion_auth.php configuration file by setting the manual\_activation option to true.

```php
<?php

$config['manual_activation']    = TRUE;
```

Now, lets take a look at our register function.

```php
<?php

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
            );
        }
        if ($this->form_validation->run() == true && $this->ion_auth->register($username, $password, $email, $additional_data))
        {
            //check to see if we are creating the user
            $this->session->set_flashdata('message', $this->ion_auth->messages());

            //redirect to login
            redirect('auth/login', 'refresh');
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
```

As you can see, we are currently setting up the registration form fields, checking the field validation and if the user input passes validation, we register the user ad redirect to the login page. Since we are now requiring a manual activation, we want to pass the user to 2Checkout to pay for the membership before we activate them. To accomplish this, we will remove the login redirect, get the user id from the database and utilize the `Twocheckout::Charge::redirect` binding.

```php
<?php

    //get user id
    $query = $this->db->query("SELECT * FROM users WHERE email = '$email'");
    $row = $query->row();
    $id = $row->id;

    //setup the checkout parameters
    $args = array(
        'sid' => 1817037,
        'mode' => "2CO",
        'li_0_name' => "Monthly Subscription",
        'li_0_price' => "1.00",
        'li_0_recurrence' => "1 Month",
        'merchant_order_id' => $id
    );

    //pass the buyer and the parameters to the checkout
    Twocheckout_Charge::redirect($args);
```

Lets take a look at what we did here. First we grabbed the the id for this user from the database and assigned it to the `$id` variable.

Then we create an array of sale parameters to pass to 2Checkout using the [Pass-Through-Products](https://www.2checkout.com/blog/knowledge-base/merchants/tech-support/3rd-party-carts/parameter-sets/pass-through-product-parameter-set/) parameter set and we assign the `$id` to the `merchant_order_id` parameter. _(The value passed with the `merchant_order_id parameter` will be passed back to our approved URL and will be returned using the `vendor_order_id` parameter on all INS messages pertaining to the sale.)_

Then we pass the parameters and the buyer to our custom checkout page on 2Checkout's secure server to complete the order.

Passback
--------

When the order is completed, 2Checkout will return the buyer and the sale parameters to the URL that we specify as the approved URL in our account. This URL can also be passed in dynamically for each sale using the `x_receipt_link_url` parameter.

Before we have a route for the approved URL, we will need to create our passback function in the order controller.

```php
<?php

    public function passback()
    {
        //Assign the returned parameters to an array.
        $params = array();
        foreach ($_REQUEST as $k => $v) {
            $params[$k] = $v;
        }

        //Check the MD5 Hash to determine the validity of the sale.
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
```

First we grab all of the parameters returned by 2Checkout and assign the to the `params` array that we created.

Then we pass the array and our secret word to the `Twocheckout::Return` binding and check the response. _(Twocheckout bindings return JSON by default or you can pass an optional third argument 'array' to return the response in an array.)_

 If the response if successful `$response['code'] == "Success"`, we activate the user and display the order success page. If the response is not successful `$response['code`] == "Fail"`, we display the order failed page.

Now we can setup our return method, enter our secret word and provide the approved URL path "http://localhost/web-widgets/index.php/order/passback" under the Site Management page in our 2Checkout admin.

**Site Management Page**

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-3.png)

**Lets try it out with a live sale.**

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-2.png)

**Enter in our billing information.**

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-4.png)

**Submit the payment.**

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-5.png)

Now the user is activated and can login to access the content that they paid for.

Notifications
-------------

2Checkout will send notifications to our application under the following circumstances.

* Order Created
* Fraud Status Changed
* Shipping Status Changed
* Invoice Status Changed
* Refund Issued
* Recurring Installment Success
* Recurring Installment Failed
* Recurring Stopped
* Recurring Complete
* Recurring Restarted

For our application, we are interested in the Fraud Status Changed message to disbale the user if the sale fails fraud review, Recurring Installment Failed to disable the user if their billing fails to bill successfully, and the Recurring Installment Success message to re-enable a disbaled user.

We are going to setup our notification function under our order controller.

```php
<?php

public function notification()
{
    //Assign the returned parameters to an array
    $params = array();
    foreach ($_POST as $k => $v) {
        $params[$k] = $v;
    }
    //Check the MD5 Hash to determine the validity of the message
    $message = Twocheckout_Notification::check($params, "tango", 'array');

    if ($message['code'] == 'Success') {
        //Preform different actions based on the message_type
        switch ($params['message_type']) {
            case 'FRAUD_STATUS_CHANGED':
                if ($params['fraud_status'] == 'fail') {
                    //Get the user id and disable access
                    $id = $params['vendor_order_id'];
                    $data = array(
                        'active' => 0
                        );
                    $this->ion_auth->update($id, $data);
                }
                break;
            case 'RECURRING_INSTALLMENT_FAILED':
                //Get the user id and disable access
                $id = $params['vendor_order_id'];
                $data = array(
                    'active' => 0
                    );
                $this->ion_auth->update($id, $data);
                break;
            case 'RECURRING_INSTALLMENT_SUCCESS':
                //Get the user id and enable access
                $user = $this->ion_auth->user()->row();
                $status = $user->active;
                if ($status == 0) {
                    $id = $params['vendor_order_id'];
                        $data = array(
                            'active' => 1,
                            'last_billed' => time(),
                            'last_invoice' => $params['invoice_id']
                            );
                    $this->ion_auth->update($id, $data);
                }
                break;
        }
    }
}
```

We grab the message parameters from the $\_POST variable and assign each key/value pair to an array.

Then we pass the array and our secret word to the `Twocheckout::Notification` binding and check the response. _(Twocheckout bindings return JSON by default or you can pass an optional third argument 'array' to return the response in an array.)_

If the response if successful `$response['code'] == "Success"`, we can preform actions based on the `message_type` parameter value.

For the Fraud Status Changed message, we will check the value of the `fraud_status` parameter and disable the user if it equals 'pass'.
For the Recurring Installment Failed message we will disable the user.
For the Recurring Installment Success message we will enable the user.

Now we can setup our Notification URL path "http://localhost/web-widgets/index.php/order/notification" and enable each message under the Notifications page in our 2Checkout admin.

![](http://github.com/2checkout/2checkout-ci-tutorial/raw/master/img/webwidgets-6.png)

Lets test our notification function. Now there are a couple ways to go about this. You can wait for the notifications to come on a live sale, or just head over to the [INS testing tool](http://developers.2checkout.com/inss) and test the messages right now. Remember the MD5 hash must match so for easy testing, you must compute the hash based on the like below:

`UPPERCASE(MD5_ENCRYPTED(sale_id + vendor_id + invoice_id + Secret Word))`

You can just use an [online MD5 Hash generator](https://www.google.com/webhp?q=md5+generator) and convert it to uppercase.

Cancel
------

We want to provide the user with the ability to cancel their membership and recurring billing without having to contact us so we already have a view setup for this. To cancel the recurring billing we will use the `Twocheckout::Sale::stop` binding to cancel all active recurring lineitems on the sale.

To accomplish this we will setup a cancel confirmation view, a cancel success page and a cancel function in our order controller.

`application/views/order/cancel.php`
```php
<?php

<?php echo form_open("<?php echo site_url().'/order/cancel' ?>");?>
    <input type="hidden" name="cancel" value="true">
    <p><button type="submit" class="btn btn-primary btn-large">Cancel Subscription</button></p>
<?php echo form_close();?>
```

`application/views/order/cancel_success.php`
```php
<div class="container">
      <h1>Membership Canceled</h1>
      <p>We have issued you a refund of $<?php echo $refund_amount; ?> for the remaining unused
        <?php echo $remaining_days; ?> days of your subscription. You will recieve the credit with in 5-7 days.</p>
</div>
```

`application/contollers/order.php`
```php
<?php

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

        //Calculate used time and refund amount
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
```
When we get the cancel confirmation from the user, we get the user's order\_number and their most recent invoice\_id.

We then set our 2Checkout API username and password using the `Twocheckout::setCredentials()` method.

Now we set the order\_number to the 'sale\_id' key in an array and pass the array to the `Twocheckout_Sale::stop()` method.

Since we are going to disable the user, we should also go ahead and refund them for the unused days in their subscription. So we use the last\_billed timestamp for the user to caclulate the unused days in their subscription.

```php
<?php

$last_bill_date = $user->last_billed;
$next_bill_date = strtotime('+1 month',$last_bill_date);
$remaining_days = floor(abs(time() - $next_bill_date)/60/60/24);
```

We can then apply that to the subscription cost to get the refund amount.

```php
$refund_amount = round((1.00 / 30) * $remaining_days, 2);
```

To submit the refund, we setup the an array that contains the invoice\_id from the most recent invoice, the refund amount and currency, and the comment and category.

```php
<?php

$args = array(
    'invoice_id' => $invoice_id,
    'category' => 5,
    'amount' => $refund_amount,
    'currency' => 'vendor',
    'comment' => 'Refunding remaining balance'
    );

Twocheckout_Sale::refund($args, 'array');
```

We then disable the user and redirect them to our cancel success page.

```php
<?php

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
```

Update
------

This last one is easy. 2Checkout provides a page for users to update their billing information on a recurring sale. We could just define the URL in our navbar. But to make it easy for them, lets pass in the order\_number when we redirect them.

`application/views/include/navbar.php`
```php
<?php

<a href="<?php echo site_url().'/order/update' ?>">Update Billing Information</a>
```

Now lets setup our update function.

`application/contollers/order.php`
```php
<?php

public function update()
{
    $user = $this->ion_auth->user()->row();
    $order_number = $user->order_number;
    redirect('https://www.2checkout.com/va/sales/customer/change_billing_method?sale_id='.$order_number, 'refresh');
}
```

Conclusion
----------

Now our application is fully integrated! Our users can register, pay for their membership and immediatly get access. We update the user based on the 2Checkout INS messages, and we provided the user with the ability to cancel the order or update their billing information.
