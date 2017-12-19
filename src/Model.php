<?php

namespace B2\Grav;

class Model extends \B2\Model
{
	function url_ex($foo) { return $this->url(); }

	function storage_engine() { return Storage::class; }

	function _host_def() { return ''; }

	function url()
	{
		$url = $this->source_file();
		$url = preg_replace('/^'.preg_quote($this->grav_root().'/user/pages/','/').'/', '/', $url);
		$url = preg_replace('!^/(\d+\.)!', '/', $url);
		$url = preg_replace('!/default\.md$!', '/', $url);

		if($host = $this->host())
			$url = "http://$host$url";

		return $url;
	}

	function body()
	{
		$html = \Michelf\MarkdownExtra::defaultTransform($this->source());
		// Пока в Michelf\Markdown нельзя задавать классы таблиц:
//		$html = str_replace("<table>", "<table class=\"{$this->layout()->table_class()}\">", $html);
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

	function keywords() { return @$this->attr['taxonomy']['tag']; }

	function slug()
	{
		$path = rtrim(dirname($this->source_file()). '/');
		return basename($path);
	}

	function modify_time()
	{
		return filemtime($this->source_file());
	}
}
