<?
namespace FlexEngine;
defined("FLEX_APP") or die("Forbidden.");
define("BLOG_FSTRUCT_PLAIN",0,false);
define("BLOG_FSTRUCT_DATED",1,false);
class blog extends module
{
	protected static $configDefault	=	array(
		"folderItems"				=>	"items",
		"folderStruct"				=>	NEWS_FSTRUCT_PLAIN,
		"imageName"					=>	"img",
		"imageUseId"				=>	true,
		"imageUseSz"				=>	true,
		"imgPrefix"					=>	"",
		"imgSzLarge"				=>	false,
		"imgSzMedium"				=>	array(330,220),
		"imgSzSmall"				=>	false,
		"itemBodyFilename"			=>	"index",
		"itemCount"					=>	20,
		"itemSilent"				=>	20,
		"inlineImgPrefix"			=>	"inline-",
		"inlineImgSzLarge"			=>	false,
		"inlineImgSzMedium"			=>	array(330,220),
		"inlineImgSzSmall"			=>	false,
		"inlineItemCount"			=>	10,
		"inlineItemSilent"			=>	10
	);

	protected static function _on_install()
	{
	}

	protected static function _on_uninstall()
	{
	}

	protected static function _on1init()
	{
	}

	protected static function _on2exec()
	{
		if(!self::silent())
		{
			self::resourceStyleAdd();
			self::resourceScriptAdd();
/*
RewriteCond %{REQUEST_FILENAME} ".self::$config["itemBodyFilename"]."\.
RewriteRule .* - [L,F]
*/
		}
	}

	protected static function _on3render($section="")
	{
		echo"Новости";
	}

	protected static function _on4sleep()
	{
	}
}
?>