# 小乌龟侦探社海龟汤快照

`ltda_soups_cc_by_sa_3.json` 是从小乌龟侦探社的[海龟汤目录](https://ltda.wikidot.com/soup-series)获取的只读快照。

站点声明：除非页面特别注明，内容采用 [Creative Commons Attribution-ShareAlike 3.0](https://creativecommons.org/licenses/by-sa/3.0/) 许可。作品版权归原作者；使用、转载或改编数据时必须保留 `author`、`source_url` 和许可信息，并以相同方式共享。

数据状态：

- `ok`：题面和汤底均已取得。
- `unused_slot`：目录中的空置题号，原页返回 404。
- `adult_verification_skipped`：原页显示成人内容验证，采集器未继续读取正文。
- `content_not_found`：公开页面未匹配到可识别的题面/汤底结构。
- `empty_or_incomplete_content`：只取得题面或汤底之一，或嵌入内容为空。
- `fetch_error`：非 404 网络或服务器错误。

重新生成：

```bash
python3 support/scrape_ltda_soups.py \
  --output database/seeds/data/ltda_soups_cc_by_sa_3.json \
  --workers 3 \
  --delay 0.45
```

采集器仅访问公开页面，保留署名和来源，不绕过成人内容验证，也不连接或写入项目数据库。
