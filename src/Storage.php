<?php

namespace B2\Grav;

class Storage extends \bors_storage
{
	var $skip_b2_fields_autoconf = true;

	function grav_root()
	{
		$grav_root = \B2\Cfg::get('grav.root');
		if(!$grav_root)
			throw new \Exception(_("Empty grav.root"));

		return $grav_root;
	}

	private function __find($object)
	{
		$basepath = ltrim($object->b2_basepath(), '/');
		$root = $this->grav_root();
		$base = $object->_basename();

		if($basepath)
			$rel_path = "{$basepath}/$base";
		else
			$rel_path = "{$base}";

		if(is_file($file = "{$root}/user/pages/$rel_path/default.md"))
			return $file;

		$dirs = glob("$root/user/pages/*.$rel_path");

//		dump("$root/user/pages/*.$rel_path", $dirs);
		foreach($dirs as $d)
			if(is_file($file = "{$d}/default.md"))
				return $file;

		return false;
	}

	function load($object)
	{
		if(file_exists($object->id()))
			$file = $object->id();
		else
			$file = $this->__find($object);

		if(!$file)
			return $object->set_is_loaded(false);

		$object->set_markup('bors_markup_markdown', false);

		return self::load_from_file($object, $file);
	}

	static function load_from_file($object, $file)
	{
		$content = file_get_contents($file);

		if(preg_match("/^---\n(.+?)\n---\n(.+)$/s", $content, $m))
		{
			// Hard test:
			if(!class_exists('Symfony\Component\Yaml\Yaml'))
				throw new \Exception("Can't find yaml extension or Symfony\Component\Yaml.\nGo to composer directory at BORS_CORE level and execute\ncomposer require symfony/yaml=*");

			$content = $m[2];
			try
			{
				$data = \bors_data_yaml::parse($m[1]);
			}
			catch(\Exception $e)
			{
				\bors_debug::syslog('error-yaml-parse', "Error in $file: " . \blib_exception::factory($e)->message());
				return $object->set_is_loaded(false);
			}

			foreach([
				'date' => [
					'create_time',
					'strtotime'
				],
			] as $md => $field)
			{
				if(!empty($data[$md]))
				{
					if(is_array($field))
						$data[$field[0]] = call_user_func($field[1], $data[$md]);
					else
						$data[$field] = $data[$md];

					unset($data[$md]);
				}
			}

			if(!empty($data['metadata']))
			{
				foreach([
					'Nav' => 'nav_name',
				] as $md => $field)
				{
					if(!empty($data['metadata'][$md]))
					{
						if(is_array($field))
							$data[$field[0]] = call_user_func($field[1], $data['metadata'][$md]);
						else
							$data[$field] = $data['metadata'][$md];

					}
				}
			}

			foreach($data as $key => $value)
			{
				$object->set_attr(bors_lower($key), $value);
				// Хм. Атрибуты не всегда работают.
				$object->set(bors_lower($key), $value, false);
			}

			if(!empty($data['metadata']))
			{
				foreach($data['metadata'] as $key => $value)
				{
					$object->set_attr(bors_lower($key), $value);
					// Хм. Атрибуты не всегда работают.
					$object->set(bors_lower($key), $value, false);
				}
			}
		}

		if(!$object->title_true())
		{
			if(preg_match('/^#\s+(.+?)\s+#$/m', $content, $m))
			{
				$object->set_title($m[1], false);
				$content = preg_replace('/^#\s+(.+?)\s+#$/m', '', $content);
			}
			elseif(preg_match('/^#\s+(.+)$/m', $content, $m))
			{
				$object->set_title($m[1], false);
				$content = preg_replace('/^#\s+(.+)$/m', '', $content);
			}
			elseif(preg_match("/(^|\n)(.+?)\n(=+)\n/s", $content, $m))
			{
				$object->set_title($m[2], false);
				$content = preg_replace("/(^|\n)(.+?)\n(=+)\n/", '', $content, 1);
			}
		}

		if(!$object->title_true())
			$object->set_title($object->_basename(), false);
//			return $object->set_is_loaded(false);

// Разные трактовки переменных в Markdown:
//	* http://assemble.io/docs/Markdown.html
//	* http://docs.runmyprocess.com/Training/Markdown_Template
//	* http://johnmacfarlane.net/pandoc/README.html
//		$content = 

		$object->set_source($content, false);
		$object->set_attr('source_file', $file);
		$object->set_attr('modify_time', filemtime($file));

		return $object->set_is_loaded(true);
	}

	function load_array($class_name, $where)
	{
		if(is_object($class_name))
			$class_name = $class_name->class_name();

//		dump($where);

		$foo = bors_foo($class_name);
		$base_path = $foo->grav_root().'/user/pages';
		$rel = $foo->rel_path();
		$rel_s = explode('/', trim($rel, '/'));

		if($g = glob("{$base_path}/[0-9][0-9].{$rel_s[0]}"))
			$path = $g[0].'/'.join('/', array_slice($rel_s, 1));
		elseif(file_exists($d = "{$base_path}/{$rel_s[0]}"))
			$path = $d.'/'.join('/', array_slice($rel_s, 1));
		else
			throw new \Exception("Not found grav base dir '$rel' at base dir '$base_path'");

		$path = rtrim($path, '/');

		if(!is_dir($path))
			throw new \Exception("Grav directory '$path' not exists");

		$dir = new \RecursiveDirectoryIterator($path);
		$it  = new \RecursiveIteratorIterator($dir);
		$files = new \RegexIterator($it, '/^.+\.md$/i', \RecursiveRegexIterator::GET_MATCH);

		$objects = [];

		foreach($files as $x)
		{
			$md_file = $x[0];
//			dump($md_file);
			$grav = call_user_func([$class_name, 'load'], $md_file);
			$objects[] = $grav;
		}

//		if($filter)
//			$pages = $filter($pages);

//		return \B2\Layout\Bootstrap3\Cards::mod_html(['items' => $pages]);


//		dump($objects);
//		exit();
		return $objects;
	}

/*
---
title: 'Индекс Карновского/Шкала ECOG-ВОЗ'
date: '11:22 06-02-2017'
taxonomy:
    tag:
        - 'Индекс Карновского'
        - 'Шкала ECOG-ВОЗ'
---

Общее состояние онкологических больных рекомендовано оценивать по индексу Карновского (0-100%) или Шкале ECOG-ВОЗ (0-4 балла).
*/

	function create($model)
	{
		$path = $model->id();
		$base_path = $model->grav_root().'/user/pages';

		$nav = $model->title() != $model->nav_name() ? $model->nav_name() : NULL;

		$data = [
			'title' => $model->title(),
			'date' => date('r', $model->create_time()),
//			'taxonomy' => [
//				'tag' => $model->keywords(),
//			],
//			'metadata' => [
//				'Nav' =>  $nav,
//			],
			'menu' => $nav,
		];

		$data = array_filter_recursive($data);

		$yaml = \Symfony\Component\Yaml\Yaml::dump($data);

		$text = $model->source();

		$text = str_replace("\r", "", $text);

		$text = preg_replace('!\[([^]]+)\|(.+)\]!', '[$2]($1)', $text);

		$text = preg_replace_callback('!^\s*(=+)\s*(.+?)\s*(=+)\s*$!m', function($m) { return str_repeat('#', strlen($m[1])).' '.$m[2]."\n"; }, $text);


		if(class_exists(\lcml_tag_pair_csv::class))
		{
			$params = [];
			$text = preg_replace_callback('!\[csv\](.+?)\[/csv\]!s', function($m) {
				$html = \lcml_tag_pair_csv::html($m[1], $params);

				$html = str_replace(' width="auto"', ' ', $html);
				$html = str_replace(' style="width:auto!important"', '', $html);
				$html = str_replace('<table >', '<table>', $html);
				$html = str_replace('<table>', '<table class="table table-striped table-bordered table-condensed">', $html);

//				$html = preg_replace('!(<table.+?)(<tr>\s*<th.+?</tr>)(.+?</table>)!s', '$1<thead>$2</thead><tbody>$3</tbody></table>', $html);

				if(class_exists(\Gajus\Dindent\Indenter::class))
				{
					$indenter = new \Gajus\Dindent\Indenter();
					$html = $indenter->indent($html);
				}

				return $html;
			}, $text);
		}

		$text = preg_replace('!\*(.+?)\*!', '**$1**', $text);

		$grav_text = "---\n".trim($yaml)."\n---\n\n" . $text;

		$file = $base_path . '/' . trim($path, '/') . '/default.md';

		if(file_exists($file) && !$model->get('__can_export_overwrite'))
			throw new \Exception(sprimtf(_("File '%s' already exists"), $file));

//		dump($file, $grav);
//		exit();
		mkpath(dirname($file), 0777);
		file_put_contents($file, $grav_text);
	}

	function each($class_name, $where)
	{
		return bors_find_all($class_name, $where);
	}
}

if(!function_exists('array_filter_recursive'))
{
	function array_filter_recursive($input)
	{
		foreach ($input as &$value)
		{
			if(is_array($value))
			{
				$value = array_filter_recursive($value);
			}
		}

		return array_filter($input); 
	}
}