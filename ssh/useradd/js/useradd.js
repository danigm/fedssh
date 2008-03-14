$(document).ready(function() {
	$(".solicitar").click(function() {
        var id = $(this).attr("id"); 
        var td = $(this).parent();
		$.ajax({
		    type: "POST",
		    cache: false,
		    url: "solicitar_ajax",
		    data: {server: id},
		    dataType: "",
		    beforeSend: function(obj) {
                td.html('<img src="loading.gif" alt="loading..."/>');
		    },
		    error: function(XMLHttpRequest, textStatus, errorThrown) {
			    alert("xxxx " + textStatus);
		    },
		    success: function(datos) {
                error = $("error", datos).text();

                if(error != ""){
                    td.html(error);
                }
                else{
                    td.html('hecho');
                }
		    }
		});
	    return false;
	    });
    });

