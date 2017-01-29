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
			$path = 'foo';

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
}
