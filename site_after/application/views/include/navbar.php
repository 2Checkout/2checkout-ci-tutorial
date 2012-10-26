<div class="navbar navbar-inverse navbar-fixed-top">
  <div class="navbar-inner">
    <div class="container">
      <a class="btn btn-navbar" data-toggle="collapse" data-target=".nav-collapse">
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
        <span class="icon-bar"></span>
      </a>
      <a class="brand" href="<?php echo site_url() ?>">Web Widgets</a>
      <div class="nav-collapse collapse">
        <ul class="nav pull-right">
          <li class="dropdown">
            <a href="" class="dropdown-toggle" data-toggle="dropdown">Account <b class="caret"></b></a>
            <ul class="dropdown-menu">
              <li><a href="<?php echo site_url().'/auth/logout' ?>">Logout</a></li>
              <li><a href="<?php echo site_url().'/auth/change_password' ?>">Change Password</a></li>
              <li class="divider"></li>
              <li class="nav-header">Billing</li>
              <li><a href="<?php echo site_url().'/order/update' ?>">Update Billing Information</a></li>
              <li><a href="<?php echo site_url().'/order/cancel' ?>">Cancel Account</a></li>
            </ul>
          </li>
        </ul>
      </div><!--/.nav-collapse -->
    </div>
  </div>
</div>