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
        <form class="navbar-form pull-right" method="post" action="<?php echo site_url().'/auth/login' ?>">
          <input class="span2" type="text" placeholder="Email" name="identity">
          <input class="span2" type="password" placeholder="Password" name="password">
          <button type="submit" class="btn">Sign in</button>
        </form>
      </div><!--/.nav-collapse -->
    </div>
  </div>
</div>