/**
 * Плагин Blog [blog]
 *
 * Версия: 1.0.0 (14.01.2014 23:02 +0400)
 * Author: Bogdan Nazar (nazar_bogdan@itechserv.ru)
 *
 * Требования: PHP FlexEngine Core 3.1.0 +
*/
(function(){

var __name_lib = "lib";
var __name_blog = "blog";
var __name_popup = "popup";
var __name_script = "blog.js";

//ищем render
if ((typeof render != "object") || (!render) || (typeof render.$name == "undefined")) {
	console.log(__name_script + " > Object [render] not found or is incompatible version.");
	return;
}
if (typeof render.pluginGet !="function") {
	console.log(__name_script + " > Object [render] has no method [pluginGet] or is incompatible version.");
	return;
}
if (typeof render.pluginRegister !="function") {
	console.log(__name_script + " > Object [render] has no method [pluginRegister] or is incompatible version.");
	return;
}

//плагин blog [worker]
var _blog = function() {
	this._initErr	=	false;
	this._inited	=	false;
	this.$name		=	__name_blog;
	this.plRender	=	null;
	this.plWorkers	=	[];
};
_blog.prototype._init = function(last) {
	if (this._inited) return true;
	if (typeof last != "boolean") last = false;
	this._inited = true;
	return true;
};
_blog.prototype.instanceStart = function(worker) {
	var args = [];
	if (arguments.length > 1) {
		for (var c in arguments) {
			if (!agruments.hasOwnProperty(c)) continue;
			if (c == "0") continue;
			args.push(arguments[c]);
		}
	}
	try {
		switch (typeof worker) {
			case "function":
				this.plWorker.push(new (worker.apply(this, args)));
				break;
			case "string":
				this.plWorkers.push(this.plRender.pluginInstanceAllocate(worker));
				break;
		}
	} catch (e) {
		this.plRender.console();
	}
	this.plInlines[this.plInlines.length - 1]._init(false, this, ids);
};
_blog.prototype.waitElement = render.sharedWaitElement;
_blog.prototype.waitPlugin = render.sharedWaitPlugin;
render.pluginRegister(new _blog(), true);

})();