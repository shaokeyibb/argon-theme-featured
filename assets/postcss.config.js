module.exports = {
	plugins: [
		require('@fullhuman/postcss-purgecss').default({
			content: [
				'../*.php',
				'../*/*.php',
				'../*/*/*.php',
				'../*/*/*/*.php',
				'./src/*.js',
				'./src/*/*.js',
				'./src/*/*/*.js',
				'./src/*/*/*/*.js',
				'./src/*.css',
				'./src/styles/*.css',
				'./src/styles/*/*.css',
				'./src/styles/*/*/*.css',
				'./src/js/*.css',
				'./src/js/*/*.css',
				'./src/js/*/*/*.css',
				'./node_modules/nouislider/dist/*.css',
				'./node_modules/bootstrap/js/src/*.js',
			],
			// 添加安全列表，确保不会误删关键样式
			safelist: {
				standard: [
					/^fa-/, 
					/^navbar/, 
					/^dropdown/, 
					/^btn/, 
					/^modal/, 
					/^tooltip/, 
					/^popover/,
					// 保护 article 元素的子元素样式
					'article',
					'body',
					'html',
				],
				deep: [
					/tippy/, 
					/iziToast/, 
					/nprogress/,
					// 保护 article 下的所有子选择器
					/^article\s/,
				],
				// 保护文章内容中可能出现的 HTML5 语义标签样式
				greedy: [
					/figcaption/,
					/figure/,
					/blockquote/,
					/cite/,
					/mark/,
					/kbd/,
					/samp/,
					/abbr/,
					/dfn/,
					/article/,
				],
			},
		})
	]
}