var listenerUrl = $.cookie( 'AmazonListenerUrl' );
if ( !listenerUrl || listenerUrl === '' ) {
	listenerUrl = '/listener/?p=amazon/listener';
}
$( "#listener_url" ).val( listenerUrl );

function fakeIpnPost( notificationType ) {
	listenerUrl = $( "#listener_url" ).val();
	$.cookie( 'AmazonListenerUrl', listenerUrl, { expires: 365, path: location.pathName } );
	$.getJSON( 'RawPosts/' + notificationType + '.json',
		function( postJson ) {
			$.ajax({
				method: 'POST',
				url: listenerUrl,
				contentType: 'application/json; charset=utf-8',
				headers: postJson.headers,
				data: JSON.stringify( postJson.body ),
				dataType: 'json'
			});
		}
	);
}

$("#capture_completed").click(function() {
	fakeIpnPost( "CaptureCompleted" );
});

$("#capture_declined").click(function() {
	fakeIpnPost( "CaptureDeclined" );
});

$("#refund_completed").click(function() {
	fakeIpnPost( "RefundCompleted" );
});

$("#refund_declined").click(function() {
	fakeIpnPost( "RefundDeclined" );
});
