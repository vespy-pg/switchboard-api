import { chromium } from 'playwright'

const url = process.argv[2]

if (!url) {
  console.error(JSON.stringify({ status: 'failed', warnings: ['Missing URL argument'] }))
  process.exit(2)
}

const safeText = (value) => {
  if (typeof value !== 'string') {
    return null
  }
  const trimmedValue = value.trim()
  return trimmedValue.length > 0 ? trimmedValue : null
}

const firstMeta = (metas, key, value) => {
  const meta = metas.find((metaItem) => metaItem[key] === value)
  return meta ? safeText(meta.content) : null
}

const normalizeUrl = (baseUrl, maybeUrl) => {
  if (!maybeUrl) {
    return null
  }
  try {
    return new URL(maybeUrl, baseUrl).toString()
  } catch {
    return null
  }
}

const main = async () => {
  const browser = await chromium.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-dev-shm-usage'
    ]
  })

  const page = await browser.newPage({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122 Safari/537.36',
    locale: 'pl-PL'
  })

  try {
    const response = await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 })
    const finalUrl = page.url()

    const statusCode = response ? response.status() : null
    if (statusCode !== null && statusCode >= 400) {
      await browser.close()
      console.log(JSON.stringify({
        status: statusCode === 403 || statusCode === 429 ? 'blocked' : 'failed',
        url,
        finalUrl,
        preview: { title: null, description: null, imageUrl: null, siteName: new URL(finalUrl).host, faviconUrl: null },
        product: null,
        warnings: [`HTTP error: ${statusCode}`]
      }))
      process.exit(0)
    }

    const metas = await page.$$eval('meta', (elements) =>
      elements
        .map((el) => ({
          name: el.getAttribute('name'),
          property: el.getAttribute('property'),
          content: el.getAttribute('content')
        }))
        .filter((m) => m.content)
    )

    const title =
      firstMeta(metas, 'property', 'og:title') ??
      firstMeta(metas, 'name', 'twitter:title') ??
      safeText(await page.title())

    const description =
      firstMeta(metas, 'property', 'og:description') ??
      firstMeta(metas, 'name', 'twitter:description') ??
      firstMeta(metas, 'name', 'description')

    const imageUrlRaw =
      firstMeta(metas, 'property', 'og:image') ??
      firstMeta(metas, 'name', 'twitter:image')

    const siteName =
      firstMeta(metas, 'property', 'og:site_name') ??
      (() => {
        try { return new URL(finalUrl).host } catch { return null }
      })()

    const faviconUrl = await page.evaluate(() => {
      const icon =
        document.querySelector('link[rel="icon"]') ||
        document.querySelector('link[rel="shortcut icon"]') ||
        document.querySelector('link[rel="apple-touch-icon"]')

      return icon ? icon.getAttribute('href') : null
    })

    const result = {
      status: 'ok',
      url,
      finalUrl,
      preview: {
        title,
        description,
        imageUrl: normalizeUrl(finalUrl, imageUrlRaw),
        siteName,
        faviconUrl: normalizeUrl(finalUrl, faviconUrl) ?? `${new URL(finalUrl).origin}/favicon.ico`
      },
      product: null,
      warnings: []
    }

    await browser.close()
    console.log(JSON.stringify(result))
  } catch (error) {
    await browser.close()
    console.log(JSON.stringify({
      status: 'failed',
      url,
      finalUrl: null,
      preview: { title: null, description: null, imageUrl: null, siteName: null, faviconUrl: null },
      product: null,
      warnings: [String(error?.message ?? error)]
    }))
  }
}

main()
