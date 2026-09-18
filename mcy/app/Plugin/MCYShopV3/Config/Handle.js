[
    {
        name: util.icon("icon-waibuduijie") + " 异次元V3.0商户配置",
        form: [
            {
                title: "协议版本",
                name: "version",
                type: "radio",
                dict: [
                    {id: 0, name: "v3.1.2 重构后全新版"},
                    {id: 1, name: "v3.1.1 之前旧版"}
                ]
            },
            {
                title: "店铺网址",
                name: "url",
                type: "input",
                placeholder: "异次元的店铺地址(如:https://abcedf.com)"
            },
            {
                title: "商户ID",
                name: "pid",
                type: "input",
                placeholder: "请输入商户ID"
            },
            {
                title: "商户密钥",
                name: "key",
                type: "input",
                placeholder: "请输入商户密钥"
            }
        ]
    }
]