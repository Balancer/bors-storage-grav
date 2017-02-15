<?php

namespace B2\Grav;

class View extends \B2\Page
{
	//TODO: на время отладки
	function can_cached() { return false; }
	function can_be_empty() { return false; }

	function storage_engine() { return Storage::class; }

	function url_ex($foo) { return $this->url(); }

	function url()
	{
		$url = $this->source_file();
		$url = preg_replace('/^'.preg_quote($this->grav_root().'/user/pages/','/').'/', '/', $url);
		$url = preg_replace('!^/(\d+\.)!', '/', $url);
		$url = preg_replace('!/default\.md$!', '/', $url);
		return $url;
	}

	function _grav_root_def() { return $this->storage()->grav_root(); }

	function pre_show()
	{
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
		return $html;
	}

	function image()
	{
		// ![](/events/opera_2016-11-21_10-07-30.png)

//		if(!preg_match('/!  \[\] \( (.+?) \)/x', $this->source(), $m))
		if(!preg_match('/!  \[ [^\]]* \] \( ([^\) ]+) [^\)]*? \)/x', $this->source(), $m))
			return NULL;

		return \bors_image_simplest::load($m[1]);
	}

	function date_interval()
	{
		return \bors_lib_date::interval(strtotime($this->get('begin')), strtotime($this->get('end')));
	}

	function card_pre_title()
	{
		$place = [];
		$place[] = $this->get('place');
		$place[] = $this->get('city');
		$place[] = $this->get('country');
		$place = join(", ", array_filter($place));
		if($place)
			$place = ". $place";
		return '<div class="text-muted"><b>'.($this->date_interval()).$place.'</b></div>';
	}

	function snippet($size = 200)
	{
		$text = $this->body();
		$text = str_replace('>', '> ', $text);
		$text = strip_tags($text);
		$text = str_replace("\n", " ", $text);
		$text = preg_replace("/\s{2,}/", ' ', $text);
		$text = str_replace('… …', '…', $text);
		$text = \blib_string::wordwrap($text, 32, ' ', true);
		return \B2\Hypher::hyphenate(clause_truncate_ceil($text, $size));
	}

	static function __unit_test($suite)
	{
		$foo = bors_foo(__CLASS__);
		$foo->set_source('*test*', false);
		$suite->assertEquals("<p><em>test</em></p>", trim($foo->body()));
	}
}
