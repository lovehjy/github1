<?php
declare (strict_types=1);

namespace App\Plugin\HimenoSena\Theme;

use App\Model\Category;
use App\Model\Site;
use App\Model\User;
use App\Service\User\Item;
use Kernel\Annotation\Inject;
use Kernel\Component\Singleton;
use Kernel\Context\Interface\Request;
use Kernel\Exception\NotFoundException;
use Kernel\Language\Language;
use Kernel\Util\Context;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class Helper extends AbstractExtension
{
    use Singleton;


    #[Inject]
    private Item $item;


    /**
     * @return TwigFunction[]
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('category_name', [$this, 'getCategoryName']),
            new TwigFunction('category_active', [$this, 'getCategoryActive']),
            new TwigFunction('keywords', [$this, 'keywords']),
        ];
    }

    /**
     * @return string
     */
    public function keywords(): string
    {
        /**
         * @var Request $request
         */
        $request = Context::get(Request::class);
        return $request->get("keywords") ?: "";
    }


    /**
     * @param int|null $cid
     * @return string
     */
    public function getCategoryActive(?int $cid = null): string
    {
        /**
         * @var Request $request
         */
        $request = Context::get(Request::class);
        return $request->get("cid") == $cid ? ' active ' : '';
    }


    /**
     * @return string
     * @throws \ReflectionException
     */
    public function getCategoryName(): string
    {
        /**
         * @var Request $request
         */
        $request = Context::get(Request::class);

        $cid = $request->get("cid") ?? null;

        $name = "推荐";
        $icon = "/app/Plugin/HimenoSena/Assets/Image/Recommend.png";

        if ($cid && $cate = Category::find($cid)) {
            $name = $cate->name;
            $icon = $cate->icon;
        }

        if ($request->get("keywords")) {
            $name = "搜索结果";
            $icon = "/app/Plugin/HimenoSena/Assets/Image/Search.png";
        }

        $name = Language::inst()->output($name);
        return <<<HTML
<div class="category-info"><img src="{$icon}" class="category-info-icon"><span class="category-info-name fs-6">{$name}</span></div>
HTML;
    }
}