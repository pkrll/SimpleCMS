<?php
/**
 * Admin Controller
 *
 * @version 1.0
 * @author  Ardalan Samimi
 * @since   Available since 0.9
 */
class AdminController extends Controller {

    public function main () {
        $this->view()->render("shared/header_admin.tpl");
        $this->view()->assign("username", Session::get("username"));
        $this->view()->render("admin/main.tpl");
        $this->view()->render("shared/footer_admin.tpl");
    }

}

?>
