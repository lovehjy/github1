[
    {
        name: "模版配置",
        form: [
            {
                title: "推荐按钮文字",
                name: "recommend_button",
                type: "textarea",
                height: 48,
                tips: "支持HTML",
                default: "推荐",
                placeholder: "按钮文字",
                required: true
            },
            {
                title: "商品打开方式",
                name: "href_blank",
                type: "radio",
                dict: [
                    {id: 0, name: "当前页"},
                    {id: 1, name: "新建页"},
                ]
            },
            {
                title: "网页底部文字",
                name: "footer_content",
                type: "textarea",
                height: 100,
                tips: "支持HTML",
                default: "本站严禁未成年人充值以及消费"
            }
        ]
    },
    {
        name: util.icon("icon-css") + " 全局CSS",
        form: [
            {
                title: false,
                name: "css",
                type: "html",
                language: "css",
                height: 560,
                tips: "全局CSS"
            }
        ]
    },
    {
        name: util.icon("icon-js") + " 全局JS",
        form: [
            {
                title: false,
                name: "javascript",
                type: "html",
                language: "javascript",
                height: 560,
                tips: "全局JS"
            }
        ]
    }
]