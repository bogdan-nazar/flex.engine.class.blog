<?
namespace FlexEngine;
defined("FLEX_APP") or die("Forbidden.");
define("BLOG_FSTRUCT_PLAIN",0,false);
define("BLOG_FSTRUCT_DATED",1,false);
define("BLOG_SLUGTYPE_CAT",0,false);
define("BLOG_SLUGTYPE_TAG",1,false);
class blog extends module
{
	private static $class			=	"";
	private static $id				=	0;
	private static $item			=	false;//полная новость/материал блога
	private static $more			=	"<!--[fe-module-blog:type=more]-->";
	private static $page			=	"";//основная страница модуля, относительно которой формируются ссылки
	private static $path			=	"";
	private static $silent			=	false;
	private static $slug			=	array(
					"alias"			=>	"unknown",
					"id" 			=>	0,
					"title"			=>	"",
					"type"			=>	BLOG_SLUGTYPE_CAT
	);
	protected static $configDefault	=	array(
		"folderItems"				=>	"items",
		"folderMedia"				=>	"media",
		"folderStruct"				=>	BLOG_FSTRUCT_DATED,
		"imageName"					=>	"",
		"imageUseId"				=>	true,
		"imageUseSz"				=>	true,
		"imgPrefix"					=>	"inline-",
		"imgSzBig"					=>	false,
		"imgSzMed"					=>	array(330,220),
		"imgSzSml"					=>	false,
		"itemCount"					=>	20,
		"itemFilename"				=>	"index",
		"itemSilent"				=>	20,
		"useAliases"				=>	true//использовать в ссылках алиасы вместо id
	);

	private static function _fetch($slug,$join,$filters=false,$order=false,$range=false,$body=true)
	{
		/*
		SELECT `i`.* FROM `fa_mod_blog` `i` INNER JOIN (
				SELECT DISTINCT `b`.`nid` FROM `fa_mod_blog_` `c`
				INNER JOIN `fa_mod_blog_binds` `b` ON `b`.`cid`=`c`.`id`
				WHERE `c`.`id`=1 OR `c`.`pid`=1) `c` ON `i`.`id`=`c`.`nid` ORDER BY `dtc` DESC LIMIT 0,4
		*/
		$t=self::tbMeta();
		$fs=array_keys($t);
		$fts=array();
		foreach($filters as $filter)
		{
			if(is_string($filter))
			{
				$fts[]=$filter;
				continue;
			}
			if(is_array($filter))
			{
				if(isset($filter[0]) && (in_array($filter[0],$fs)) && isset($filter[1]) && isset($filter[2]))
				{
					if(($t[$filter[0]]["type"]=="string") || ($t[$filter[0]]["type"]=="text"))
					{
						$filter[2]="".$filter[2];
						$type="string";
					}
					else $type="other";
					$fts[]=array($type,$filter[2],"AND",$filter[0],$filter[1]);
				}
			}
		}
		$t="i";
		$fts=db::filtersMake($fts,true,true,false,$t);
		$q="SELECT `".$t."`.*,`a`.`name` as `username` FROM ".self::tb(self::$class)." `".$t."`
				INNER JOIN ".self::tb("auth")." `a` ON `".$t."`.`uid`=`a`.`id`".
			(($join!==false)?("
				INNER JOIN (
					SELECT DISTINCT `b`.`nid` FROM ".self::tb(self::$class."_".$join)." `c`
					INNER JOIN ".self::tb(self::$class."_binds")." `b` ON `b`.`cid`=`c`.`id`
					WHERE `c`.`id`=".$slug.
					($join!="tags"?(" OR `c`.`pid`=".$slug):"")."
				) `c` ON `".$t."`.`id`=`c`.`nid`"
			):"").
			($fts?(" WHERE ".$fts):"");
		if(!is_array($order))
		{
			$order=array("dtc","DESC");
			$c=2;
		}
		else $c=count($order);
		if($c)$q.=" ORDER BY `".$order[0]."`".(isset($order[1])?(" ".$order[1]):"");
		if(is_int($range))
		{
			$range=array(0,$range);
			$c=2;
		}
		elseif(!is_array($range))
		{
			$range=array(0,(self::$silent?self::config("itemCount"):self::config("itemSilent")));
			$c=2;
		}
		else $c=count($range);
		if($c>0)$q.=" LIMIT ".($c==1?("0,".$range[0]):($range[0].",".$range[1]));
		//выполняем запрос
		$r=self::q($q,!self::$silent);
		//если ничего не найдено, возвращаем false
		if($r===false)return false;
		//статус magic_quotes_runtime
		$mqrt=self::mquotes_runtime();
		//структура каталогов с контентом
		$folderStruct=self::config("folderStruct");
		//кофигурируемое имя директории с контентными данными
		$dirItems=self::config("folderItems");
		//кофигурируемое имя файла с контентом
		$contentName=self::config("itemFilename");
		//сюда сохраним выборку
		$items=array();
		//переменная для назначения дополнительного CSS-класса первому посту
		$cnt=0;
		//обходим все записи и дополняем каждую из них
		//данными для шаблона
		while($rec=@mysql_fetch_assoc($r))
		{
			$rec["id"]=0+$rec["id"];
			$rec["date"]=self::dtR($rec["dt"]);
			if($mqrt)
			{
				$rec["title"]=stripslashes($rec["title"]);
				$rec["subtitle"]=stripslashes($rec["subtitle"]);
			}
			//год и месяц новости
			$itemYr=substr($rec["dt"],0,4);
			$itemMon=substr($rec["dt"],5,2);
			//путь к новости "по умолчанию"
			if(self::$configDefault["useAliases"] && $rec["alias"])$path=$rec["alias"];
			else $path=$rec["id"];
			if($folderStruct!=BLOG_FSTRUCT_PLAIN)$path=$itemYr."/".$itemMon."/".$path;
			//ссылка на новость
			if(self::$page)
			{
				$rec["link"]=$path;
				$rec["link_popup"]="";
			}
			//если у блога нет своей страницы, то
			//вместо перехода используем popUp
			else
			{
				$rec["link"]="javascript:void(0);";
				$rec["link_popup"]=" onclick=\"flexClient.getModule(\"".self::$class."\").itemPopup(".$rec["id"].")\"";
			}
			$be=false;//body exists?
			//полный путь в файловой системе относительно корня движка
			$path_root=FLEX_APP_DIR_DAT."/_".self::$class."/".$dirItems."/";
			if($rec["path"])
			{
				//путь к телу новости, прописанный в базе
				$path_php=$path_root.$rec["path"]."/".$rec["id"];
				$be=@file_exists($path_php.$contentName.".html");
			}
			if(!$be)
			{
				$path_php=$path_root.$itemYr."/".$itemMon."/".$rec["id"];
				//проверяем, существует ли файл с контентом
				$be=@file_exists($path_php."/".$contentName.".html");
			}
			//загружаем контент
			if($body)
			{
				if($be)
				{
					$rec["body"]=@file_get_contents($path_php."/".self::config("itemFilename").".html");
					if($mqrt)$rec["body"]=stripslashes($rec["body"]);
				}
				else $rec["body"]=_t(LANG_BLOG_IBODY_MOVED);
			}
			//получаем рубрики
			$q="
			SELECT
				`c`.`id`, `c`.`alias`, `c`.`title`
			FROM
				".self::tb(self::$class."_cats")." `c`
			INNER JOIN
				".self::tb(self::$class."_binds")." `b`
				ON
				(`c`.`id`=`b`.`cid` OR `c`.`pid`=`b`.`cid`)
			WHERE
				`b`.`nid`=".$rec["id"]." AND `b`.`type`=0
			ORDER BY `c`.`pid`, `c`.`title`";
			//выполняем запрос
			$r1=self::q($q,!self::$silent);
			//если ничего не найдено, возвращаем false
			if($r1===false)return false;
			//сохраняем рубрики, формируя для них ссылки
			$cats=array();
			while($rec1=@mysql_fetch_assoc($r1))
			{
				$rec1["id"]=0+$rec["id"];
				$rec1["cat-title"]=$rec1["title"];
				unset($rec1["title"]);
				$rec1["cat-link"]=FLEX_APP_DIR_ROOT.(self::$page?(self::$page."/"):"")."?cat=".($rec1["alias"]?$rec1["alias"]:$rec1["id"]);
				unset($rec1["id"]);
				$cats[]=$rec1;
			}
			$rec["cats"]=$cats;
			//получаем теги
			$q="
			SELECT
				`t`.`id`, `t`.`tag`
			FROM
				".self::tb(self::$class."_tags")." `t`
			INNER JOIN
				".self::tb(self::$class."_binds")." `b`
				ON
				`t`.`id`=`b`.`cid`
			WHERE
				`b`.`nid`=".$rec["id"]." AND `b`.`type`=1
			ORDER BY `t`.`tag`";
			//выполняем запрос
			$r1=self::q($q,!self::$silent);
			//если ничего не найдено, возвращаем false
			if($r1===false)return false;
			//сохраняем рубрики, формируя для них ссылки
			$tags=array();
			while($rec1=@mysql_fetch_assoc($r1))
			{
				$rec1["id"]=0+$rec["id"];
				$rec1["tag-title"]=$rec1["tag"];
				unset($rec1["tag"]);
				$rec1["tag-link"]=FLEX_APP_DIR_ROOT.(self::$page?(self::$page."/"):"")."?tag=".$rec1["id"];
				unset($rec1["id"]);
				$tags[]=$rec1;
			}
			$rec["tags"]=$tags;
			//получаем список привязанных медиа-ресурсов
			$imgs=self::mediaFetchArray($rec["id"],array("par1"=>array(self::config("imgPrefix")."sml",self::config("imgPrefix")."med",self::config("imgPrefix")."big")));
			if($imgs===false)
			{
				if(!self::$silent)
				{
					$msg=self::mediaLastErr();
					if(!$msg)$msg="Ошибка получения списка изображений.";
					self::msgAdd($msg,MSGR_TYPE_ERR);
				}
				return;
			}
			$rec["noimg-sml"]=" noimg sml";
			$rec["noimg-med"]=" noimg med";
			$rec["noimg-big"]=" noimg big";
			if(count($imgs))
			{
				foreach($imgs as $img)
				{
					if($img["par1"]==self::config("imgPrefix")."sml")
					{
						$rec["img-sml-bg"]="background-image:url('".$img["path_url"]."');";
						$rec["img-sml-src"]=FLEX_APP_DIR_ROOT.$img["path_file"];
						$rec["noimg-sml"]="";
					}
					if($img["par1"]==self::config("imgPrefix")."med")
					{
						$rec["img-med-bg"]="background-image:url('".$img["path_url"]."');";
						$rec["img-med-src"]=FLEX_APP_DIR_ROOT.$img["path_file"];
						$rec["noimg-med"]="";
					}
					if($img["par1"]==self::config("imgPrefix")."big")
					{
						$rec["img-big-bg"]="background-image:url('".$img["path_url"]."');";
						$rec["img-big-src"]=FLEX_APP_DIR_ROOT.$img["path_file"];
						$rec["noimg-big"]="";
					}
				}
			}
			if($rec["noimg-sml"])
			{
				$rec["img-sml-bg"]="";
				$rec["img-sml-src"]="";
			}
			if($rec["noimg-med"])
			{
				$rec["img-med-bg"]="";
				$rec["img-med-src"]="";
			}
			if($rec["noimg-big"])
			{
				$rec["img-big-bg"]="";
				$rec["img-big-src"]="";
			}
			if($cnt)$rec["first"]="";
			else $rec["first"]=" first";
			$cnt++;
			$items[]=$rec;
		}
		if(!count($items))$items=false;
		return $items;
	}

	private static function _rate($id)
	{
	}

	/**
	* Функция проверяет валидность рубрики/тега
	*
	* @param mixed $slug
	*/
	private static function _slugGet($slug=false)
	{
		$ret=true;
		$def=false;
		$fldName="";
		$fldTitle="";
		$table="";
		$where="";
		//если функция запущена без параметров,
		//то проверяем слаг "по умолчанию" (полученный в _on1init)
		if($slug===false)
		{
			$slug=array_merge(array(),self::$slug);
			$ret=false;
		}
		else
		{
			//пытаемся преобразовать слаг
			//из строки типа cat=1
			if(is_string($slug))
			{
				$slug=explode("=",$slug);
				if(count($slug)>1)
				{
					$slug=array(
						"id"=>0+$slug[1],
						"type"=>($slug[0]=="cat"?BLOG_SLUGTYPE_CAT:BLOG_SLUGTYPE_TAG)
					);
				}
				else return false;
			}
			else
			{
				//возвращаем false если структура слага неизвестна
				if(!is_array($slug) || !isset($slug["id"]))return false;
			}
		}
		//если не задан id слага, то считаем что этот слаг является
		//рубрикатором по умолчанию
		if(!$slug["id"])
		{
			$def=true;
			$where="`def`=1";
			$slug["type"]=BLOG_SLUGTYPE_CAT;
		}
		//если id состоит только из цифр
		//то ищем по БД id
		if(!$def && (is_integer($slug["id"]) || ctype_digit($slug["id"])))$where="`id`=".$slug["id"];
		//задаем параметры SQL-запроса
		if($slug["type"]==BLOG_SLUGTYPE_CAT)
		{
			$fldName="alias";
			$fldTitle=",`title`";
			$table=self::tb(self::$class."_cats");
			if(!$def && !$where)$where="`alias`='".@mysql_real_escape_string($slug["id"])."'";
		}
		else
		{
			$slug["type"]=BLOG_SLUGTYPE_TAG;
			$fldName="tag";
			$fldTitle="";
			$table=self::tb(self::$class."_tags");
			if(!$where)$where="`tag`='".@mysql_real_escape_string($slug["id"])."'";
		}
		//ищем рубрику или тег
		$r=self::q("SELECT `id`,`".$fldName."`".$fldTitle." FROM ".$table." WHERE ".$where,!self::$silent);
		//при ошибке в БД выходм,
		//возвращая false или обнуляя slug["id"]
		if($r===false)//on silent
		{
			if($ret)return false;
			else
			{
				self::$slug["id"]=0;
				return;
			}
		}
		//если запрос выполнился штатно идем дальше
		$rec=@mysql_fetch_assoc($r);
		//если нашлись записи, то сохраняем данные и выходим
		//или выходим с возвращением данных
		if($rec)
		{
			//восстанавливаем целостность базы (если нужно)
			if($def)
			{
				$id1=$rec["id"];
				$rec1=@mysql_fetch_assoc($r);
				if($rec1)self::q("UPDATE ".$table." SET `def`=0 WHERE `id`!=".$id1." AND ".$where,!self::$silent);
			}
			//
			if($ret)
			{
				$rec["id"]=0+$rec["id"];
				$rec["type"]=$slug["type"];
				if($slug["type"]==BLOG_SLUGTYPE_TAG)
				{
					$rec["alias"]=$rec["tag"];
					$rec["title"]=$rec["tag"];
					unset($rec["tag"]);
				}
				return $rec;
			}
			else
			{
				self::$slug["id"]=0+$rec["id"];
				if($slug["type"]==BLOG_SLUGTYPE_TAG)
				{
					self::$slug["alias"]=$rec["tag"];
					self::$slug["title"]=$rec["tag"];
				}
				else
				{
					self::$slug["alias"]=$rec["alias"];
					self::$slug["title"]=$rec["title"];
				}
			}
		}
		//если ничего не нашлось...
		else
		{
			if($ret)return false;
			else self::$slug["id"]=0;
		}
	}

	protected static function _hookLangData($i)
	{
		define("LANG_BLOG_EMPTY_RES_CAT",$i++);
		define("LANG_BLOG_EMPTY_RES_TAG",$i++);
		define("LANG_BLOG_IBODY_MOVED",$i++);
		define("LANG_BLOG_ILABEL_AUTHOR",$i++);
		define("LANG_BLOG_ILABEL_CATS",$i++);
		define("LANG_BLOG_ILABEL_TAGS",$i++);
		define("LANG_BLOG_ITEM_NOTFOUND",$i++);
		define("LANG_BLOG_MORE",$i++);
		define("LANG_BLOG_NO_ENTRIES",$i++);
		return array(
			"ru-Ru"	=> array(
				LANG_BLOG_EMPTY_RES_CAT			=>	"В данной рубрике еще нет записей",
				LANG_BLOG_EMPTY_RES_TAG			=>	"По данному тегу ничего не найдено",
				LANG_BLOG_IBODY_MOVED			=>	"Содержимое записи перемещено или удалено",
				LANG_BLOG_ILABEL_AUTHOR			=>	"Автор",
				LANG_BLOG_ILABEL_CATS			=>	"Рубрики",
				LANG_BLOG_ILABEL_TAGS			=>	"Тэги",
				LANG_BLOG_ITEM_NOTFOUND			=>	"Запись не найдена",
				LANG_BLOG_MORE					=>	"Далее",
				LANG_BLOG_NO_ENTRIES			=>	"Блог пуст"
			),
			"en-Gb"	=> array(
				LANG_BLOG_EMPTY_RES_CAT			=>	"No entries under the current rubric",
				LANG_BLOG_EMPTY_RES_TAG			=>	"No entries matched the current tag",
				LANG_BLOG_IBODY_MOVED			=>	"Post content was moved or deleted",
				LANG_BLOG_ILABEL_AUTHOR			=>	"Author",
				LANG_BLOG_ILABEL_CATS			=>	"Rubrics",
				LANG_BLOG_ILABEL_TAGS			=>	"Tags",
				LANG_BLOG_ITEM_NOTFOUND			=>	"Entry not found",
				LANG_BLOG_MORE					=>	"More",
				LANG_BLOG_NO_ENTRIES			=>	"Blog is empty"
			)
		);
	}

	protected static function _on1init()
	{
		self::$class=self::_class();
		self::$silent=self::silent();
		self::$path=self::path();
		$s=explode("/",self::$path["sections"]);
		$l=count($s);
		//непустое свойство self::$page указывает на режим "главной страницы";
		//считаем, что "главной"  является страница, привязанная к основному методу
		//рендеринга - "__render", пустая строка также интерпретируется как "__render"
		//(см. таблицу байндингов (привязок) - "fa_mod_render_binds");
		//если к __render привязано несколько страниц, (!!!) то берется ПЕРВАЯ
		self::$page=self::pageByModMethod(self::modHookName("render"));
		//если привязанная страница найдена (режим "главной страницы"),
		//то пытаемся найти id текущего материала (blog item),
		//который должен следовать сразу за слагом главной страницы
		//например, /myblog/my-article/ или /myblog/1/
		if(self::$page)
		{
			//сохраняем self::$page в промежуточную переменную
			//так как оригинальное значение self::$page нам еще
			//пригодится позже
			if(self::$page==self::pageIndex())$check="";
			else $check=self::$page;
			$id="";
			//ищем найденную страницу в текущем URL
			for($c=($l-1);$c>=0;$c--)
			{
				//считаем, что id поста присутствует в последнем сегменте URL при двух условиях:
				//	1. имя "главной" страницы блога ($check) не пустое (найдено соответствие в БД)
				//		и совпадает с одним из сегментов URL ($s[$c])
				//	2. имя "главной" страницы блога ($check) пустое (найдено соотвествие в БД, но имя очищено выше,
				//		так как совпадает с именем главной страницей сайта, которая явно указывается только с аргументами модулей)
				//		и при этом индекс ($c) проверяемого сегмента не должен быть последним, так как последний сегмент
				//		должен указывать на id поста, а в данном случае он укажет на алиас текущей страницы
				if(($s[$c]===$check) || (!$check && ($s[$c]===self::pageIndex()) && ($c!=($l-1))))
				{
					//если "главная" страница блога найдена, то сохраняем алиас поста,
					//который лежит в последнем сегменте URL
					$id=$s[$l-1];
					break;
				}
			}
			//если найден $id, то сохраняем его
			if($id)
			{
				if(ctype_digit($id))$id=0+$id;
				self::$id=$id;
			}
			//если $id нет, то пытаемся найты параметры
			//вывода списка материалов (рубрика или тег)
			else
			{
				if(isset($_GET["cat"]))
				{
					self::$slug["id"]=$_GET["cat"];
					self::$slug["type"]=BLOG_SLUGTYPE_CAT;
				}
				else
				{
					if(isset($_GET["tag"]))
					{
						self::$slug["id"]=$_GET["tag"];
						self::$slug["type"]=BLOG_SLUGTYPE_TAG;
					}
				}
			}
		}
	}

	protected static function _on2exec()
	{
		if(!self::silent())
		{
			self::resourceStyleAdd();
		}
		//если задан режим "главной страницы"
		if(self::$page)
		{
			//если задан id статьи, то проверяем ее и фетчим
			//статью, если id валидное
			if(self::$id)
			{
				$filters=array(
					array((is_string(self::$id)?"alias":"id"),"=",self::$id),
					array("act","=",1)
				);
				self::$item=self::_fetch(false,false,$filters);
			}
			//если id не найдено, то проверяем слаг по базе
			else
			{
				self::_slugGet();
			}
		}
	}

	protected static function _on3render($spot,$tpl="",$slug="",$count=0,$load_body=true,$btn_more=true,$retain_cur_path=true)
	{
		//если задан $id то пытаемся найти и отобразить
		//материал и выходим
		if(self::$id)
		{
			$t=self::tplGet("main-single");
			if(self::$item)
			{
				self::_rate(self::$item[0]["id"]);
				if(!$t->error())
				{
					$t->setCont("noentry","");
					$t->setVar("label-author",_t(LANG_BLOG_ILABEL_AUTHOR));
					$t->setVar("label-cats",_t(LANG_BLOG_ILABEL_CATS));
					$t->setVar("label-tags",_t(LANG_BLOG_ILABEL_TAGS));
					self::$item[0]["body"]=preg_replace("/".preg_quote(self::$more)."/","",self::$item[0]["body"]);
					$t->setArray(self::$item[0]);
				}
			}
			else
			{
				$t->setVar("noentry",_t(LANG_BLOG_ITEM_NOTFOUND));
				$t->setCont("entry","");
			}
			$t->_render();
			return;
		}
		//отображаем список...
		if(!$tpl)$tpl="main";
		$count=0+$count;
		if(is_string($load_body))$load_body=(($load_body==="true") || ($load_body==="1"));
		if(is_string($btn_more))$btn_more=(($btn_more==="true") || ($btn_more==="1"));
		//$retain_cur_path используется при формировании ссылки на отдельный пост
		//ссылка может быть сформирована:
		// - либо с указанием на текущую страницу
		// - либо на "главную" страницу блога
		if(is_string($retain_cur_path))$retain_cur_path=(($retain_cur_path==="true") || ($retain_cur_path==="1"));
		if(!self::$slug["id"])
		{
			//проверяем слаг, переданный в параметрах
			if($slug)
			{
				$slug=self::_slugGet($slug);
				//если указанный слаг оказался некорректным
				//то находим "рубрику по умолчанию"
				if($slug===false)
				{
					if(!self::$page)self::_slugGet();
					$slug=self::$slug;
				}
			}
		}
		else $slug=self::$slug;
		//выясняем c какой таблицей делаем JOIN
		// - cats или tags
		$join=($slug["type"]==BLOG_SLUGTYPE_CAT?"cats":"tags");
		//получаем список материалов
		$items=self::_fetch($slug["id"],$join,$filters,false,$count,$load_body);
		if($items && self::$page)
		{
			//формируем полную ссылку на пост для каждого поста
			$parts=explode("/".$slug["alias"],self::$path["sections"]);
			$c=count($parts);
			if(($c>1) && ($parts[$c-1]==""))
			{
				array_pop($parts);
				$sects=implode("/".$slug["alias"],$parts);
			}
			else $sects=self::$path["sections"];
			$sects=($retain_cur_path?($sects?($sects."/"):""):"").self::$page."/";
			foreach($items as $key=>$item)
			{
				$items[$key]["link-abs"]=FLEX_APP_DIR_ROOT.$sects.$items[$key]["link"];
				//делаем ссылку "Далее"
				if($load_body && $btn_more)
				{
					$bparts=explode(self::$more,$items[$key]["body"]);
					$items[$key]["body"]=$bparts[0];
					if(count($bparts)>1)
					{
						$more=self::tplGet("more");
						$more->setVar("link",$items[$key]["link-abs"]);
						$more->setVar("label",_t(LANG_BLOG_MORE));
						$items[$key]["body"].=$more->_render(true);
					}
				}
			}
		}
		$t=self::tplGet($tpl);
		if(!$t->error())
		{
			if($items)
			{
				$t->setCont("noentries","");
				$t->setVar("label-author",_t(LANG_BLOG_ILABEL_AUTHOR));
				$t->setVar("label-cats",_t(LANG_BLOG_ILABEL_CATS));
				$t->setVar("label-tags",_t(LANG_BLOG_ILABEL_TAGS));
				$t->setArrayCycle("entries",$items);
			}
			else
			{
				$t->setVar("noentries",_t($slug["id"]?(($slug["type"]==BLOG_SLUGTYPE_TAG)?LANG_BLOG_EMPTY_RES_TAG:LANG_BLOG_EMPTY_RES_CAT):LANG_BLOG_NO_ENTRIES));
				$t->setCont("entries","");
			}
			$t->_render();
		}
		else
		{

		}
	}

	protected static function _on4sleep()
	{

	}

	/**
	* Generates custom menu entries
	*
	*/
	public static function menuGen($menu)
	{
		$item["id"]=0;
		$item["cid"]=0;
		$item["extid"]=0+$place;
		$item["target"]="_self";
		$item["atitle"]="";
		$item["alias"]="";
		$item["par1"]="";
		$item["par2"]="";
		$item["par3"]="";
		//$item["link"]=self::$rootPath."ucp.php?mode=logout&sid=".self::$user->data["session_id"]."&redirect=".$_SERVER["REQUEST_URI"];
		$item["link"]="/plugins";
		$item["title"]="Плагины для WordPress";
		return $item;
	}
}
?>