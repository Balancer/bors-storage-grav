<?php

namespace B2\Grav;

class SubPagesList extends \B2\Page
{
	//TODO: на время отладки
	function can_cached() { return false; }
	function can_be_empty() { return false; }

	function storage_engine() { return Storage::class; }

	function pre_show()
	{
		$this->b2_app()->debug();
		// Если путь не оканчивается на слеш, редиректим. Иначе могут быть проблемы с относительными путями.
		if(bors()->request()->url_match('![^/]$!'))
			return go($this->url().'/');

		config_set('cache_disabled', true);
		return parent::pre_show();
	}

//	function cache_static() { return rand(10*86400, 30*86400); }

	function _template_def()
	{
		$this->add_template_data('skip_page_title', true);
		$this->add_template_data('skip_page_admin', true);

		return parent::_template_def();
	}

	function body()
	{
		$html = \Michelf\MarkdownExtra::defaultTransform($this->source());
		// Пока в Michelf\Markdown нельзя задавать классы таблиц:
		$html = str_replace("<table>", "<table class=\"{$this->layout()->table_class()}\">", $html);

		$html = preg_replace_callback('!<b2>(.+?)</b2>!', function($match) {
			list($class_name, $method) = explode('::', $match[1]);

			if(!preg_match('/^[\w\\\\]+$/', $class_name))
				return "Incorrect class name '$class_name' in b2 markup plugin {$match[1]}";

			if(!preg_match('/^(\w+)\(\)$/', $method, $m))
				return "Incorrect method name '$method' in b2 markup plugin {$match[1]}";

			$method = $m[1];

			$method = 'b2_markup_plugin_'.$method;

			if(!method_exists($class_name, $method))
				return "Method '$method' not exists in class '$class_name' for b2 markup plugin {$match[1]}";

			return call_user_func([$class_name, $method], $this);

			return "$class_name:$method";

		}, $html);

		return $html;
	}

	static function b2_markup_plugin_list($grav)
	{
		$sub_pages = glob(dirname($grav->get('source_file')).'/*/*.md');
		$pages = [];
		foreach($sub_pages as $md_file)
		{
			$page = \B2\Grav\View::load($md_file);
//			var_dump($page->source_file(), $page->url(), $page->titled_link());
			$pages[] = $page;
		}

		return \B2\Layout\Bootstrap3\Cards::mod_html(['items' => $pages]);

		$result = [];

		foreach($pages as $page)
			$result[] = "<li>{$page->get('begin')}: {$page->titled_link()}</li>";

		return "<ul>".join("\n", $result)."</ul>";
	}
}

