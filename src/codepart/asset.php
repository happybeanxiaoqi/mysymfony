<?php
/**
 * Packages实现的是 PackageInterface接口，它定义了如下两个方法:
 * getVersion()    返回一个资源的asset版本。
 * getUrl()  返回绝对路径，或返回相对于根目录的公开路径（public path）。
 * **/ 
/**使用asset包
 * Asset组件的一个主要功能，是具有对程序资源的版本进行管理的能力。
 * Asset version常用于控制资源的缓存。
 * **/ 
// 一个包被创建并管理着没有版本功能的资源：
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\EmptyVersionStrategy;
 
$package = new Package(new EmptyVersionStrategy());
echo $package->getUrl('/image.png');
// result: /image.png

版本化资源
/**StaticVersionStrategy 用于加挂 v1 后缀到任何意的资源路径:**/ 
use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\VersionStrategy\StaticVersionStrategy;
$package = new Package(new StaticVersionStrategy('v1'));
echo $package->getUrl('/image.png');
// result: /image.png?v1

// put the 'version' word before the version value
// 在版本值的前面放一个 'version' 字样
$package = new Package(new StaticVersionStrategy('v1', '%s?version=%s'));
echo $package->getUrl('/image.png');
// result: /image.png?version=v1
 
// put the asset version before its path
// 在路径前面放置资源的版本
$package = new Package(new StaticVersionStrategy('v1', '%2$s/%1$s'));
echo $package->getUrl('/image.png');
// result: /v1/image.png


自定义版本策略
使用 VersionStrategyInterface 来定义你自己的版本策略。
例如，你的程序可能需要附加当前日期到所有的web资源，以便在每天销毁缓存：
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
class DateVersionStrategy implements VersionStrategyInterface
{
    private $version;
    public function __construct()
    {
        $this->version = date('Ymd');
    }
    public function getVersion($path)
    {
        return $this->version;
    }
    public function applyVersion($path)
    {
        return sprintf('%s?v=%s', $path, $this->getVersion($path));
    }
}


资源群组 
一般来讲，很多资源都被存放于常见路径下 (如 /static/images)。
如果你也是如此，用 PathPackage 来替换默认的 Package 类，
即可避免一次又一次地重复那个路径:
use Symfony\Component\Asset\PathPackage;
$package = new PathPackage('/static/images', new StaticVersionStrategy('v1'));
echo $package->getUrl('/logo.png');
// result: /static/images/logo.png?v1

如果你在项目中同时使用了 HttpFoundation 组件 
(例如，在一套Symfony程序中)，那么 PathPackage 可以统管当前请求的上下文:
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\Context\RequestStackContext;
// ...
$package = new PathPackage(
    '/static/images',
    new StaticVersionStrategy('v1'),
    new RequestStackContext($requestStack)
);
echo $package->getUrl('/logo.png');
// result: /somewhere/static/images/logo.png?v1


绝对Assets和CDNs
那些把资源存放在不同的域名和CDN下的程序 (Content Delivery Networks) 
应该使用 UrlPackage 类，以生成各自资源的绝对URL:
use Symfony\Component\Asset\UrlPackage;
// ...
$package = new UrlPackage(
    'http://static.example.com/images/',
    new StaticVersionStrategy('v1')
);
echo $package->getUrl('/logo.png');
// result: http://static.example.com/images/logo.png?v1
你也可以传一个schema-agnostic（译注：是否https未知）的URL：
use Symfony\Component\Asset\UrlPackage;
// ...
$package = new UrlPackage(
    '//static.example.com/images/',
    new StaticVersionStrategy('v1')
);
echo $package->getUrl('/logo.png');
// result: //static.example.com/images/logo.png?v1

如果你把资源托在多个域名上以改进程序性能，
传入一个URL的数组到 UrlPackage 构造器的第一个参数：
use Symfony\Component\Asset\UrlPackage;
// ...
$urls = array(
    '//static1.example.com/images/',
    '//static2.example.com/images/',
);
$package = new UrlPackage($urls, new StaticVersionStrategy('v1'));
echo $package->getUrl('/logo.png');
// result: http://static1.example.com/images/logo.png?v1
echo $package->getUrl('/icon.png');
// result: http://static2.example.com/images/icon.png?v1

知晓“请求上下文”的资源 ¶
与相对于程序的资源类似，绝对资源（absolute assets）也能够统管当前请求的上下文。下例中，只有考虑了请求的scheme时，才能选出合适的基本路径
（对于HTTPs请求，是HTTPs链接或者“与协议相关”的链接，对于HTTP请求则是任意的base URL）。
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\Context\RequestStackContext;
// ...
$package = new UrlPackage(
    array('http://example.com/', 'https://example.com/'),
    new StaticVersionStrategy('v1'),
    new RequestStackContext($requestStack)
);
echo $package->getUrl('/logo.png');
// assuming the RequestStackContext says that we are on a secure host
// 假设RequestStackContext说我们正处于安全连接
// result: https://example.com/logo.png?v1




已命名的包 
管理着许多资源的程序可能需要把它们打包成相同的版本策略和基本路径。
Asset组件包含了一个 Packages 的类以简化多个assets的管理。
下例中，所有的包都使用相同的版本策略（versioning strategy），
但他们却有着不同的基本路径（base paths）：

use Symfony\Component\Asset\Package;
use Symfony\Component\Asset\PathPackage;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\Asset\Packages;
// ...
$versionStrategy = new StaticVersionStrategy('v1');
$defaultPackage = new Package($versionStrategy);
$namedPackages = array(
    'img' => new UrlPackage('http://img.example.com/', $versionStrategy),
    'doc' => new PathPackage('/somewhere/deep/for/documents', $versionStrategy),
);
$packages = new Packages($defaultPackage, $namedPackages)
echo $packages->getUrl('/main.css');
// result: /main.css?v1
echo $packages->getUrl('/logo.png', 'img');
// result: http://img.example.com/logo.png?v1
echo $packages->getUrl('/resume.pdf', 'doc');
// result: /somewhere/deep/for/documents/resume.pdf?v1