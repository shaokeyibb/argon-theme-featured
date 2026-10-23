import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/editor';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { SelectControl, TextareaControl, TextControl, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const choices = {
  default: __('跟随全局设置', 'argon'), true: __('启用', 'argon'), false: __('禁用', 'argon'),
  always: __('一直显示', 'argon'), never: __('永不显示', 'argon'),
  replace: __('替换全局设置', 'argon'), append: __('附加在全局设置后', 'argon'), none: __('不使用', 'argon')
};
const fields = [
  ['argon_hide_readingtime', '隐藏字数和阅读时间', ['false', 'true']],
  ['argon_meta_simple', '简化文章 Meta', ['false', 'true']],
  ['argon_first_image_as_thumbnail', '首图作为头图', ['default', 'true', 'false']],
  ['argon_show_post_outdated_info', '文章过时提示', ['default', 'always', 'never']],
  ['argon_enable_external_link_clicks', '外链点击统计', ['default', 'true', 'false']],
  ['argon_after_post', '文末附加 HTML', null, 'raw'],
  ['argon_custom_css', '自定义 CSS', null, 'raw'],
  ['argon_ai_post_summary', 'AI 摘要', ['true', 'false'], 'ai'],
  ['argon_ai_no_update_post_summary', '保留已有 AI 摘要', ['default', 'true', 'false'], 'ai'],
  ['argon_ai_extra_prompt_mode', '额外 Prompt 模式', ['default', 'replace', 'append', 'none'], 'ai'],
  ['argon_ai_extra_prompt', '额外 Prompt', null, 'ai'],
  ['argon_enable_discourse_comments', 'Discourse 评论', ['false', 'true'], 'discourse'],
  ['argon_discourse_comments_topic_id', 'Discourse 主题 ID', null, 'discourse']
];

function PostSettings() {
  const { meta, postId, type, saving } = useSelect(select => ({
    meta: select('core/editor').getEditedPostAttribute('meta') || {},
    postId: select('core/editor').getCurrentPostId(),
    type: select('core/editor').getCurrentPostType(),
    saving: select('core/editor').isSavingPost()
  }), []);
  const { editPost, savePost } = useDispatch('core/editor');
  const [busy, setBusy] = useState(false), [message, setMessage] = useState(null);
  const config = window.argonEditorSettings || {};
  if (!['post', 'page'].includes(type)) return null;
  const regenerate = async () => {
    setBusy(true); setMessage(null);
    try {
      await savePost();
      const response = await fetch(config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: new URLSearchParams({
        action: 'regenerate_ai_post_summary', post_id: postId, argon_meta_box_nonce: config.nonce
      }), signal: AbortSignal.timeout(40000) });
      const result = await response.json();
      if (!response.ok || result.status !== 'success') throw new Error(__('重新生成失败', 'argon'));
      setMessage({ status: 'success', text: __('重新生成成功', 'argon') });
    } catch (error) { setMessage({ status: 'error', text: error.message }); }
    finally { setBusy(false); }
  };
  return <PluginDocumentSettingPanel name="argon-post-settings" title={__('Argon 文章设置', 'argon')}>
    {fields.filter(([, , , scope]) => !scope || (scope === 'raw' ? config.canUnfilteredHtml : config[`${scope}Enabled`])).map(([key, label, values]) => {
      const props = { key, label: __(label, 'argon'), value: meta[key] || '', onChange: value => editPost({ meta: { ...meta, [key]: value } }), __nextHasNoMarginBottom: true };
      if (values) return <SelectControl {...props} __next40pxDefaultSize options={[{ value: '', label: choices.default }, ...values.map(value => ({ value, label: choices[value] }))]} />;
      return key.endsWith('topic_id') ? <TextControl {...props} __next40pxDefaultSize /> : <TextareaControl {...props} />;
    })}
    {config.aiEnabled && <Button variant="secondary" isBusy={busy} disabled={busy || saving} onClick={regenerate}>{__('重新生成 AI 摘要', 'argon')}</Button>}
    {message && <Notice status={message.status} onRemove={() => setMessage(null)}>{message.text}</Notice>}
  </PluginDocumentSettingPanel>;
}
registerPlugin('argon-post-settings', { render: PostSettings });
