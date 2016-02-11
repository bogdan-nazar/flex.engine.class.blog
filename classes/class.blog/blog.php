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
	private static $page			=	"";
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
		"itemSilent"				=>	20
	);

	private static function _fetch($slug,$join,$filters=false,$order=false,$range=false,$body=true)
	{
		$t=db::tMeta(self::$class);
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
		if($join!==false)$t="i";
		else $t="";
		$fts=db::filtersMake($fts,true,true,false,$t);
		$q="SELECT ".($t?("`".$t."`."):"")."* FROM ".self::tb(self::$class)." `".$t."`".
		($t?("INNER JOIN (
		SELECT DISTINCT `b`.`nid` FROM ".self::tb(self::$class."_".$join)." `c` INNER JOIN "
		.self::tb(self::$class."_binds")." `b` ON `b`.`cid`=`c`.`id` WHERE `c`.`id`=".$slug.
		($join!="tags"?(" OR `c`.`pid`=".$slug):"").") `c` ON `".$t."`.`id`=`c`.`nid`"):"").
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
		$r=self::q($q,!self::$silent);
		if($r===false)return false;
		$recs=array();
		$mqrt=self::mquotes_runtime();
		$items=array();
		while($rec=@mysql_fetch_assoc($r))
		{
			$rec["id"]=0+$rec["id"];
			$rec["date"]=self::dtR($rec["dt"]);
			if($mqrt)
			{
				$rec["title"]=stripslashes($rec["title"]);
				$rec["subtitle"]=stripslashes($rec["subtitle"]);
			}
			$rec["link"]=($rec["alias"]?$rec["alias"]:$rec["id"]);
			if(self::config("folderStruct")==BLOG_FSTRUCT_PLAIN)
			{
				$path=$rec["path"];
				$path2="/".substr($rec["dt"],0,4)."/".substr($rec["dt"],5,2);
			}
			else
			{
				$path="/".substr($rec["dt"],0,4)."/".substr($rec["dt"],5,2);
				$path2=$rec["path"];
			}
			$path_php=FLEX_APP_DIR_DAT."/_".self::$class."/".self::config("folderItems").$path."/".$rec["id"];
			$be=@file_exists($path_php."/".self::config("itemFilename").".html");
			if(!$be)
			{
				$path_php=FLEX_APP_DIR_DAT."/_".self::$class."/".self::config("folderItems").$path2."/".$rec["id"];
				$be=@file_exists($path_php."/".self::config("itemFilename").".html");
				if($be)$path=$path2;
				else $path_php=FLEX_APP_DIR_DAT."/_".self::$class."/".self::config("folderItems").$path."/".$rec["id"];

			}
			$path_http=FLEX_APP_DIR_ROOT.$path_php;
			if($body)
			{
				if($be)
				{
					$rec["body"]=@file_get_contents($path_php."/".self::config("itemFilename").".html");
					if($mqrt)$rec["body"]=stripslashes($rec["body"]);
				}
				else $rec["body"]=$rec["subtitle"];
			}
			$imgs=self::mediaFetchArray($rec["id"],array("par1"=>array(self::config("imgPrefix")."sml",self::config("imgPrefix")."med",self::config("imgPrefix")."big")));
			if($imgs===false)
			{
				if(!self::$silent)
				{
					$msg=self::lastErr();
					if(!$msg)$msg="Ошибка получения списка изображений.";
					self::msgAdd($msg,MSGR_TYPE_ERR);
				}
				return;
			}
			$rec["noimg-sml"]=" noimg sml";
			$rec["noimg-med"]=" noimg med";
			$rec["noimg-big"]=" noimg big";
			if(!count($imgs))
			{
				//пытаемся найти по имени
				$path=$path_php."/".self::config("folderMedia");
				$rec["img-sml-src"]=$path."/".self::config("imgPrefix")."sml.jpg";
				$rec["img-med-src"]=$path."/".self::config("imgPrefix")."med.jpg";
				$rec["img-big-src"]=$path."/".self::config("imgPrefix")."big.jpg";
				if(@file_exists($rec["img-sml-src"]))
				{
					$rec["img-sml-bg"]="background-image:url('".(FLEX_APP_DIR_ROOT.$rec["img-sml-src"])."');";
					$rec["img-sml-src"]=FLEX_APP_DIR_ROOT.$rec["img-sml-src"];
					$rec["noimg-sml"]="";
				}
				if(@file_exists($rec["img-med-src"]))
				{
					$rec["img-med-bg"]="background-image:url('".(FLEX_APP_DIR_ROOT.$rec["img-med-src"])."');";
					$rec["img-med-src"]=FLEX_APP_DIR_ROOT.$rec["img-med-src"];
					$rec["noimg-med"]="";
				}
				if(@file_exists($rec["img-big-src"]))
				{
					$rec["img-big-bg"]="background-image:url('".(FLEX_APP_DIR_ROOT.$rec["img-big-src"])."');";
					$rec["img-big-src"]=FLEX_APP_DIR_ROOT.$rec["img-big-src"];
					$rec["noimg-big"]="";
				}
			}
			else
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

	private static function _slugGet($slug=false)
	{
		$ret=true;
		if($slug===false)
		{
			$slug=array_merge(array(),self::$slug);
			$ret=false;
		}
		if(!$slug["id"])
		{
			$r=self::q("SELECT `id`,`alias`,`title` FROM ".self::tb(self::$class."_cats")." WHERE `def`=1",!self::$silent);
			if($r===false)//on silent
			{
				if($ret)return false;
				else
				{
					self::$slug["id"]=0;
					return;
				}
			}
			$rec=@mysql_fetch_assoc($r);
			if($rec)
			{
				if($ret)
				{
					$rec["id"]=0+$rec["id"];
					$rec["type"]=BLOG_SLUGTYPE_CAT;
					return $rec;
				}
				else
				{
					self::$slug["id"]=0+$rec["id"];
					self::$slug["alias"]=$rec["alias"];
					self::$slug["title"]=$rec["title"];
				}
				$rec=@mysql_fetch_assoc($r);
				if($rec)self::q("UPDATE ".self::tb(self::$class."_cats")." SET `def`=0 WHERE `id`!=".$slug["id"],!self::$silent);
			}
			else
			{
				if($ret)return false;
				else
				{
					self::$slug["id"]=0;
					return;
				}
			}
		}
		else
		{
			if($slug["type"]==BLOG_SLUGTYPE_CAT)
			{
				$r=self::q("SELECT `id`,`alias`,`title` FROM ".self::tb(self::$class."_cats")." WHERE `alias`='".@mysql_real_escape_string($slug["id"])."'",!self::$silent);
				if($r===false)//on silent
				{
					if($ret)return false;
					else
					{
						self::$slug["id"]=0;
						return;
					}
				}
				$rec=@mysql_fetch_assoc($r);
				if($rec)
				{
					if($ret)
					{
						$rec["id"]=0+$rec["id"];
						$rec["type"]=BLOG_SLUGTYPE_CAT;
						return $rec;
					}
					else
					{
						self::$slug["id"]=0+$rec["id"];
						self::$slug["alias"]=$rec["alias"];
						self::$slug["title"]=$rec["title"];
					}
				}
				else
				{
					if(ctype_digit($slug["id"]))
					{
						$r=self::q("SELECT `id`,`alias`,`title` FROM ".self::tb(self::$class."_cats")." WHERE `id`=".$slug["id"],!self::$silent);
						if($r===false)//on silent
						{
							if($ret)return false;
							else
							{
								self::$slug["id"]=0;
								return;
							}
						}
						$rec=@mysql_fetch_assoc($r);
						if($rec)
						{
							if($ret)
							{
								$rec["id"]=0+$rec["id"];
								$rec["type"]=BLOG_SLUGTYPE_CAT;
								return $rec;
							}
							else
							{
								self::$slug["id"]=0+$rec["id"];
								self::$slug["alias"]=$rec["alias"];
								self::$slug["title"]=$rec["title"];
							}
						}
					}
					else
					{
						if($ret)return false;
						else
						{
							self::$slug=0;
							return;
						}
					}
				}
			}
			else
			{
				$r=self::q("SELECT `id`,`tag` FROM ".self::tb(self::$class."_tags")." WHERE `tag`='".@mysql_real_escape_string($slug["id"])."'",!self::$silent);
				if($r===false)//on silent
				{
					if($ret)return false;
					else
					{
						self::$slug["id"]=0;
						self::$slug["type"]=BLOG_SLUGTYPE_CAT;
						return;
					}
				}
				$rec=@mysql_fetch_assoc($r);
				if($rec)
				{
					if($ret)
					{
						$slug["id"]=0+$rec["id"];
						$slug["alias"]="tag-".$rec["id"];
						$slug["title"]=$rec["tag"];
						return $slug;
					}
					else
					{
						self::$slug["id"]=0+$rec["id"];
						self::$slug["alias"]="tag-".$rec["id"];
						self::$slug["title"]=$rec["tag"];
					}
				}
				else
				{
					if(ctype_digit(self::$slug))
					{
						$r=self::q("SELECT `id` FROM ".self::tb(self::$class."_tags")." WHERE `id`=".$slug["id"],!self::$silent);
						if($r===false)//on silent
						{
							if($ret)return false;
							else
							{
								self::$slug["id"]=0;
								self::$slug["type"]=BLOG_SLUGTYPE_CAT;
								return;
							}
						}
						$rec=@mysql_fetch_assoc($r);
						if($rec)
						{
							if($ret)
							{
								$slug["id"]=0+$rec["id"];
								$slug["alias"]="tag-".$rec["id"];
								$slug["title"]=$rec["tag"];
								return $slug;
							}
							else
							{
								self::$slug["id"]=0+$rec["id"];
								self::$slug["alias"]="tag-".$rec["id"];
								self::$slug["title"]=$rec["tag"];
							}
						}
					}
					else
					{
						if($ret)return false;
						else
						{
							self::$slug["id"]=0;
							self::$slug["type"]=BLOG_SLUGTYPE_CAT;
							return;
						}
					}
				}
			}
		}
	}

	protected static function _on1init()
	{
		self::$class=self::_class();
		self::$silent=self::silent();
		self::$path=self::path();
		$s=explode("/",self::$path["sections"]);
		$l=count($s);
		self::$page=self::pageByModMethod(self::modHookName("render"));
		$id="";
		for($c=($l-1);$c>=0;$c--)
		{
			if($s[$c]==self::$page)break;
			if($s[$c])
			{
				$id=$s[$c];
				break;
			}
		}
		if($id)
		{
			if(@ctype_digit($id))self::$id=0+$id;
			else self::$id=$id;
		}
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

	protected static function _on2exec()
	{
		if(!self::silent())
		{
			self::resourceStyleAdd();
		}
		self::_slugGet();
	}

	protected static function _on3render($tpl="",$slug="",$count=0,$load_body=false,$btn_more=false,$retain_cur_path=true)
	{
		if(!$tpl)$tpl="main";
		$count=0+$count;
		if(is_string($load_body))$load_body=(($load_body==="true") || ($load_body==="1"));
		if(is_string($btn_more))$btn_more=(($btn_more==="true") || ($btn_more==="1"));
		if(is_string($retain_cur_path))$retain_cur_path=(($retain_cur_path==="true") || ($retain_cur_path==="1"));
		if(self::$id)
		{
			$filters=array(
				array((is_string(self::$id)?"alias":"id"),"=",self::$id),
				array("act","=",1)
			);
			$cur=self::_fetch(false,false,$filters);
			if($cur)
			{
				self::_rate($cur[0]["id"]);
				$t=self::tplGet($tpl."-single");
				if($t->error() && $tpl!="main")$t=self::tplGet("main-single");
				if(!$t->error())
				{
					$t->setArray($cur[0]);
					$t->_render();
					return;
				}
			}
		}
		$slug=array("id"=>$slug);
		$slug["type"]=BLOG_SLUGTYPE_CAT;
		if($slug["id"])
		{
			$p=explode("=",$slug["id"]);
			if(count($p)!=2)$slug["id"]="";
			else
			{
				if($p[0]=="tag")$slug["type"]=BLOG_SLUGTYPE_TAG;
				$slug["id"]=$p[1];
				$slug=self::_slugGet($slug);
				if($slug===false)
				{
					$slug=array();
					$slug["id"]="";
					$slug["type"]=BLOG_SLUGTYPE_CAT;
				}
			}
		}
		if(!$slug["id"])
		{
			if(self::$slug["id"])
				$slug=array_merge(array(),self::$slug);
		}
		if(!$slug["id"])$items=false;
		{
			if($slug["type"])$join="tags";
			else $join="cats";
			$filters=array(
				array("act","=",1)
			);
			$items=self::_fetch($slug["id"],$join,$filters,false,$count,$load_body);
			if($items)
			{
				foreach($items as $key=>$item)
				{
					$p=explode("/".$slug["alias"],self::$path["sections"]);
					$l=count($p);
					if($l>1 && $p[$l-1]=="")
					{
						array_pop($p);
						$p=implode("/".$slug["alias"],$p);
					}
					else $p=self::$path["sections"];
					$l=FLEX_APP_DIR_ROOT.(($retain_cur_path?($p."/"):"").(self::$page?self::$page:$slug["alias"])."/");
					$items[$key]["link-abs"]=$l.$items[$key]["link"];
				}
			}
		}
		$t=self::tplGet($tpl);
		if($cur)
		{
			$c=array();
			foreach($cur[0] as $key=>$val)$c["c".$key]=$val;
			$t->setArray($c);
		}
		if($items)$t->setArrayCycle("entries",$items);
		$t->setVar("btn-more",$btn_more?"btn-more":"");
		$t->setVar("cur-path",self::$path["sections"]);
		$t->setVar("page",self::$page);
		$t->_render();
	}

	protected static function _on3sleep()
	{

	}
}
?>