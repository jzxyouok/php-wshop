<?php
/**
 * Created by PhpStorm.
 * User: QQ: 209900956
 * oschina:http://git.oschina.net/codejun
 * Author：软件开发，网站建设/
 * Date: 2016/7/15
 * Time: 14:35
 */

namespace Home\Controller;


/**
 * 单页控制器
 * Class PagesController
 * @package Home\Controller
 */
class PagesController extends HomeController
{


    public function page()
    {
        $what = I("type");//传递页面的信息

    }

    public function about()
    {

        $this->display("Pages/about");
    }

    public function index()
    {
        $this->display("Pages/index");

    }

    public function news()
    {
        $this->display("Pages/about");
    }

}