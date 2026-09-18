!function () {
    let loadIndex = 0;

    if (util.isPc()) {
        $(document).pjax('a[target!=_blank]', '#sena-container', {fragment: '#sena-container', timeout: 8000});
        $(document).on('pjax:send', function () {
            loadIndex = layer.load();
        });
        $(document).on('pjax:complete', function () {
            layer.close(loadIndex);
            $('html, body').animate({
                scrollTop: $('.category-items')?.offset()?.top - 8
            }, 10);
        });
        $("a[target!=_blank]").click(function () {
            $('a[target!=_blank]').parent().removeClass("active");
            $(this).parent().addClass("active");
        });
    } else {
        if (util.getParam("cid") > 0) {
            $('html, body').animate({
                scrollTop: $('.category-items')?.offset()?.top - 8
            }, 30);
        }
    }

    $('.input-search').on('keydown', function (event) {
        if (event.key === 'Enter' || event.keyCode === 13) {
            $.pjax({
                url: `/?keywords=${$('.input-search').val()}`,
                container: '#sena-container',
                fragment: '#sena-container',
                timeout: 8000
            });
        }
    });
}();