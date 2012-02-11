function setDriverState () {
	var newState = 0;
	var newStateRB = $('newstate');
	if (newStateRB) {
		newState = $$('input[name=newstate]:checked')[0].value;
	}
	stateManager.setOptions( {
		data : {
			'state' : newState,
		}
	}).send();
}

window.addEvent('domready', function() {

	stateManager = new Request( {
		method : 'post',
		url : 'setstate.php',
		data : {
			'state' : 0
		},
		onRequest : function() {
		},
		onSuccess : function(responseText) {
			$('currentState').set('html', responseText);
		}
	});
	
	stateManager.setOptions( {
		data : {
			'state' : 0,
		}
	}).send();

});