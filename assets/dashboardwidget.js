const wfac = 1.5;
google.charts.load( 'current', { 'packages': ['gauge'] } );
google.charts.setOnLoadCallback( wfDrawChart );

function wfGetData() {
	var xmlHttp = new XMLHttpRequest;
	xmlHttp.open( "GET", encodeURI( wFcPanelSettings.dataURI + '?secret=' + wFcPanelSettings.secret + '&token=' + wFcPanelSettings.token + '&host=' + wFcPanelSettings.host + ( wFcPanelSettings.abs == null || wFcPanelSettings.abs.trim().length === 0 ? '' : '&abs=' + wFcPanelSettings.abs ) ), false );
	xmlHttp.send();
	
	if ( xmlHttp.status >= 200 && xmlHttp.status < 300 ) {
		var cos = JSON.parse( xmlHttp.getResponseHeader( 'X-Threads_connected' ) );
		var jsondata = JSON.parse( xmlHttp.responseText );
		var cpdata = jsondata.hasOwnProperty( 'result' ) ? jsondata.result.data : jsondata.data;

		if ( cpdata.hasOwnProperty( 'errors' ) && cpdata.errors != null && cpdata.errors.lenght > 0 ) {
			return '{ sts: 406 }';
		} else {
			mu = cpdata.findIndex( obj => obj.name === 'Memory Used' );
//			sw = cpdata.findIndex( obj => obj.name === 'Swap'        );
			sl = cpdata.findIndex( obj => obj.name === 'Server Load' );
			cc = cpdata.findIndex( obj => obj.name === 'CPU Count'   );
			return {
				mem: Math.ceil( cpdata[ mu ].value.substring( 0, cpdata[ mu ].value.length - 1 ) ),
				cos: cos,
//				swa: Math.ceil( cpdata[ sw ].value.substring( 0, cpdata[ sw ].value.length - 1 ) ),
//				loa: Math.ceil( 100 * cpdata[ sl ].value.substring( 0, cpdata[ sl ].value.length - 1 ) / cpdata[ cc ].value ),
				loa: Math.ceil( 100 * cpdata[ sl ].value / cpdata[ cc ].value ),
				sel: cpdata[ sl ].value,
				cps: cpdata[ cc ].value,
			};
		}
	} else {
		return '{ sts: xmlHttp.status }';
	}
}

function wfDrawChart() {
	var values = wfGetData();

	if ( values.hasOwnProperty( 'sts' ) && values.sts !== 200 ) {
		console.log( wFcPanelSettings.dataURI + ' status ' + values.sts );
	} else {
		var data = google.visualization.arrayToDataTable( [
			[ 'Label', 'Value' ],
			[ wFcPanelSettings.labels.mem, values.mem ],
//			[ wFcPanelSettings.labels.swa, values.swa ],
			[ wFcPanelSettings.labels.con, values.cos.co ],
			[ wFcPanelSettings.labels.loa, values.loa ],
		] );
		const element = document.getElementById( wFcPanelSettings.chartID ).parentElement.parentElement;
		var width   = wfac * element.offsetWidth;
		var options = {
				 width: width,   height: width / 5 - 5,
			   redFrom:    90,    redTo:       100,
			yellowFrom:    75, yellowTo:        90,
			minorTicks:     5
		};
		var chart = new google.visualization.Gauge( document.getElementById( wFcPanelSettings.chartID ) );
		chart.draw( data, options );
		let alltds = element.querySelectorAll( 'table tbody td' );
		let v = [ values.mem + '%', values.cos.tc + ' / ' + values.cos.mc, values.sel + ' / ' + values.cps ];
		alltds.forEach( ( item, i ) => item.setAttribute( 'title', v[i] ) );

		setInterval( function() {
			width = wfac * element.offsetWidth;
			options.width  = width;
			options.height = width / 5 - 5;
			values = wfGetData();

			if ( ! element.classList.contains( 'closed' ) && element.offsetParent !== null ) {
				data.setValue( 0, 1, values.mem );
				data.setValue( 1, 1, values.cos.co );
//				data.setValue( 1, 1, values.swa );
				data.setValue( 2, 1, values.loa );
				chart.draw( data, options );
		let v = [ values.mem + '%', values.cos.tc + ' / ' + values.cos.mc, values.sel + ' / ' + values.cps ];
				alltds.forEach( ( item, i ) => item.setAttribute( 'title', v[i] ) );
			}
		}, 1000 * wFcPanelSettings.interval );
	}
}
