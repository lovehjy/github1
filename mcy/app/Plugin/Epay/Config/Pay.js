[
    {
        name: util.icon("icon-waibuduijie") + " 对接配置",
        form: [
            {
                title: "支付网关",
                name: "url",
                type: "input",
                placeholder: "支付网关地址(如:https://abcedf.com)"
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
            },
            {
                title: "MAPI",
                name: "mapi",
                type: "switch",
                tips: "如果对方系统支持MAPI，那么可以启用这个，可以大幅度提高通讯安全"
            }
        ]
    }
]