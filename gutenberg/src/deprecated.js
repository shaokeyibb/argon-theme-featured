/** Frozen v2 serializers. Preserve historic class/style quirks for block validation. */
import { isWhitespaceCharacter } from 'is-whitespace-character';
const calcTranslucentColor = color => color.length === 7 ? color + '33' : color;
const getStyleClass = (className = '') => className.includes('mini') ? 'github-info-card-mini' : 'github-info-card-full';
export const legacyBlocks = {
'argon/alert': { apiVersion: 1, attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		content: {
			type: 'string',
			default: ''
		},
		fa_icon_name: {
			type: 'string',
			default: 'info-circle'
		},
	}, save: (props) => {
		return (
			<div className="alert" style={{ backgroundColor: props.attributes.color }}>
				{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
					<span className="alert-inner--icon">
						<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>
					</span>
				}
				<span className="alert-inner--text" dangerouslySetInnerHTML={{ __html: props.attributes.content }}></span>
			</div>
		);
	} },
'argon/admonition': { apiVersion: 1, attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		title: {
			type: 'string',
			default: ''
		},
		content: {
			type: 'string',
			default: ''
		},
		fa_icon_name: {
			type: 'string',
			default: ''
		},
		show_title: {
			type: 'bool',
			default: false
		},
		show_content: {
			type: 'bool',
			default: true
		},
	}, save: (props) => {
		return (
			<div className="admonition shadow-sm" style={{ borderLeftColor: props.attributes.color }}>
				{props.attributes.show_title &&
					<div className="admonition-title" style={{ backgroundColor: props.attributes.color + "33" }}>
						{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
							<span>
								<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>&nbsp;
							</span>
						}
						<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
					</div>
				}
				{props.attributes.show_content &&
					<div className="admonition-body" dangerouslySetInnerHTML={{ __html: props.attributes.content }}/>
				}
			</div>
		);
	} },
'argon/collapse': { apiVersion: 1, attributes: {
		color: {
			type: 'string',
			default: '#ffffff00'
		},
		title: {
			type: 'string',
			default: ''
		},
		content: {
			type: 'string',
			default: ''
		},
		fa_icon_name: {
			type: 'string',
			default: ''
		},
		collapsed: {
			type: 'bool',
			default: true
		},
		show_left_border: {
			type: 'bool',
			default: false
		}
	}, save: (props) => {
		return (
			<div className={`collapse-block shadow-sm ${props.attributes.collapsed ? "collapsed" : ""} ${props.attributes.show_left_border ? "" : "hide-border-left"}`}  style={{ borderLeftColor: props.attributes.color }}>
				<div className="collapse-block-title" style={{ backgroundColor: calcTranslucentColor(props.attributes.color) }}>
					{!(isWhitespaceCharacter(props.attributes.fa_icon_name) || props.attributes.fa_icon_name == "") &&
						<span>
							<i className={`fa fa-${props.attributes.fa_icon_name}`}></i>&nbsp;
						</span>
					}
					<span className="collapse-block-title-inner" dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
					<i className="collapse-icon fa fa-angle-down"></i>
				</div>
				<div className="collapse-block-body" style={props.attributes.collapsed ? "display: none" : ""} dangerouslySetInnerHTML={{ __html: props.attributes.content }}/>
			</div>
		);
	} },
'argon/github': { apiVersion: 1, attributes: {
		author: {
			type: 'string',
			default: ''
		},
		project: {
			type: 'string',
			default: ''
		}
	}, save: (props) => {
		return (
			<div className={`github-info-card card shadow-sm ${getStyleClass(props.className || props.attributes.className)}`} data-author={props.attributes.author} data-project={props.attributes.project}>
				<div className="github-info-card-header">
					<a href="https://github.com/" ref="nofollow" target="_blank" title="Github" rel="noopener">
						<span><i className="fa-brands fa-github"></i>&nbsp;GitHub</span>
					</a>
				</div>
				<div className="github-info-card-body">
					<div className="github-info-card-name-a">
						<a href={`https://github.com/${props.attributes.author}/${props.attributes.project}`} target="_blank" rel="noopener">
							<span className="github-info-card-name">{props.attributes.author}/{props.attributes.project}</span>
						</a>
					</div>
					<div className="github-info-card-description"></div>
				</div>
				<div className="github-info-card-bottom">
					<span className="github-info-card-meta github-info-card-meta-stars">
						<i className="fa fa-star"></i>&nbsp;<span className="github-info-card-stars"></span>
					</span>
					<span className="github-info-card-meta github-info-card-meta-forks">
						<i className="fa fa-code-fork"></i>&nbsp;<span className="github-info-card-forks"></span>
					</span>
				</div>
			</div>
		);
	} },
'argon/timeline': { apiVersion: 1, attributes: {
		list: {
			type: 'array',
			default: [{
				time: '',
				title: '',
				content: '',
				id: 0,
				deleted: false
			}]
		}
	}, save: (props) => {
		const nodelist = props.attributes.list.map((node) => {
			if (node.deleted == true){
				return;
			}
			return (
				<div class="argon-timeline-node">
					{(node.time != "") &&
						<div className="argon-timeline-time" dangerouslySetInnerHTML={{ __html: node.time }}></div>
					}
					<div class="argon-timeline-card card bg-gradient-secondary shadow-sm">
						{(node.title != "") &&
							<div className="argon-timeline-title" dangerouslySetInnerHTML={{ __html: node.title }}></div>
						}
						{(node.content != "") &&
							<div className="argon-timeline-content" dangerouslySetInnerHTML={{ __html: node.content }}></div>
						}
					</div>
				</div>
			);
		});
		return (
			<div>
				<div class="argon-timeline">
					{nodelist}
				</div>
			</div>
		);
	} },
'argon/progressbar': { apiVersion: 1, attributes: {
		color: {
			type: 'string',
			default: '#7889e8'
		},
		title: {
			type: 'string',
			default: ''
		},
		progress: {
			type: 'number',
			default: 50
		},
		show_title: {
			type: 'bool',
			default: true
		}
	}, save: (props) => {
		return (
			<div class="progress-wrapper">
				<div class="progress-info">
					{props.attributes.show_title &&
						<div class="progress-label">
							<span dangerouslySetInnerHTML={{ __html: props.attributes.title }}/>
						</div>
					}
					<div class="progress-percentage">
						<span>{props.attributes.progress}%</span>
					</div>
				</div>
				<div class="progress">
					<div class="progress-bar" style={{ width: props.attributes.progress.toString() + "%", backgroundColor: props.attributes.color }} />
				</div>
			</div>
		);
	} },
'argon/todolist': { apiVersion: 1, attributes: {
		list: {
			type: 'array',
			default: [{
				content: '',
				checked: false,
				id: 0,
				deleted: false
			}]
		}
	}, save: (props) => {
		const nodelist = props.attributes.list.map((node) => {
			if (node.deleted == true){
				return;
			}
			return (
				<div class="shortcode-todo custom-control custom-checkbox">
					<input class="custom-control-input" type="checkbox" checked={node.checked ? true : null}/>
					<label class="custom-control-label">
						<span dangerouslySetInnerHTML={{ __html: node.content }}/>
					</label>
				</div>
			);
		});
		
		return (
			<div style="margin-bottom: 20px;">
				{nodelist}
			</div>
		);
	} },
'argon/tabpanel': { apiVersion: 1, attributes: {
		list: {
			type: 'array',
			default: [{
				title: '',
				content: '',
				id: 0,
				deleted: false
			}]
		},
		id: {
			type: 'string',
			default: ''
		},
		active: {
			type: 'number',
			default: 0
		},
		has_animation: {
			type: 'boolean',
			default: true
		},
	}, save: (props) => {
		const tabTitleNodeList = props.attributes.list.map((node) => {
			if (!node.deleted){
				return (
					<li className="nav-item">
						<a
							className={"nav-link " + (props.attributes.active == node.id ? 'active' : '')} 
							dangerouslySetInnerHTML={{__html: node.title}}
							id={`${props.attributes.id}-${node.id}-tab`}
							href={`#${props.attributes.id}-${node.id}`}
							data-toggle="tab"
							role="tab"
							aria-controls={`#${props.attributes.id}-${node.id}`}
							aria-selected="false"
						/>
					</li>
				)
			}
		});

		const tabContentNodeList = props.attributes.list.map((node) => {
			if (!node.deleted){
				return (
					<div
						className={`tab-pane ${props.attributes.has_animation ? ' fade' : ''} ${props.attributes.active == node.id ? ' active show' : ''}`}
						dangerouslySetInnerHTML={{__html: node.content}}
						id={`${props.attributes.id}-${node.id}`}
						role="tabpanel"
						aria-labelledby={`${props.attributes.id}-${node.id}-tab`}>
					</div>
				)
			}
		});
		
		return (
			<div class="argon-tabpanel">
				<div class="tabpanel-header nav-wrapper">
					<ul class="nav nav-pills nav-fill" role="tablist">
						{tabTitleNodeList}
					</ul>
				</div>
				<div class="tabpanel-body card card-body shadow-sm">
					<div class="tab-content">
						{tabContentNodeList}
					</div>
				</div>
			</div>
		);
	} },
};
