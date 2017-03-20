/**
 * Плагин Blog [blog]
 *
 * Версия: 1.0.0 (14.01.2014 23:02 +0400)
 * Author: Bogdan Nazar (nazar_bogdan@itechserv.ru)
 *
 * Требования: PHP FlexEngine Core 3.1.0 +
*/
(function(){

var __name_this = "blog";
var __name_script = __name_this + ".js";

//ищем FlexClient
if ((typeof window.FlexClient != "object") || !window.FlexClient) {
	console.log(__name_script + " > Client application is not found.");
	return;
}
if (typeof window.FlexClient.modReg != "function") {
	console.log(__name_script + " > Client application has no method [modReg] or is incompatible version.");
	return;
}
if (typeof window.FlexClient.modInst != "function") {
	console.log(__name_script + " > Client application has no method [modInst] or is incompatible version.");
	return;
}

var _blog = function() {
	this._initErr	=	false;
	this._inited	=	false;
	this.$name		=	__name_blog;
	this.plRender	=	null;
	this.plWorkers	=	[];

	var _init = function(last) {
		if (this._inited) return true;
		if (typeof last != "boolean") last = false;
		this._inited = true;
		return true;
	},

	_instance = function(section) {
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
})();