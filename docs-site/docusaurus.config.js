// @ts-check
const config = {
  title: 'AI Chat Plugin Docs',
  tagline: 'WordPress AI chat with OpenAI/Claude and optional RAG',
  favicon: 'img/favicon.ico',
  url: 'https://example.com',
  baseUrl: '/',
  organizationName: 'your-org',
  projectName: 'aichat-docs',
  onBrokenLinks: 'throw',
  onBrokenMarkdownLinks: 'warn',
  i18n: { defaultLocale: 'es', locales: ['es', 'en'] },
  presets: [
    [
      'classic',
      /** @type {import('@docusaurus/preset-classic').Options} */ (
      {
        docs: {
          routeBasePath: '/',
          sidebarPath: require.resolve('./sidebars.js'),
          editUrl: undefined,
        },
        blog: false,
        theme: { customCss: require.resolve('./src/css/custom.css') }
      })
    ]
  ],
  themeConfig: {
    navbar: { title: 'AI Chat Plugin', logo: { alt: 'AI Chat', src: 'img/logo.svg' } },
    footer: { style: 'dark', copyright: `© ${new Date().getFullYear()} AI Chat` }
  }
};

module.exports = config;
