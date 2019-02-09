define(['jquery', 'core/ajax'], function($, ajax) {
    var mod = {
		showDialog: function(title, body, fnOnShow, buttons) {
			$('#dlg_title').html(title);
			$('#dlg_body').html(body);
			if(fnOnShow) {
				fnOnShow();
			}
			if(buttons) {
				var html = '';
				for(var i = 0; i < buttons.length; i++) {
					var button = buttons[i];
					html += '<button class="btn btn-secondary" id="dlg_btn_' + button.id + '" ' + (button.close?'data-dismiss="modal"':'') + '>' + button.text + '</button> ';
				}
				$('#dlg_footer').html(html);
				
				for(var i = 0; i < buttons.length; i++) {
					var button = buttons[i];
					if(button.onClick) {
						$('#dlg_btn_' + button.id).click(button.onClick);
					}  
				}
			} else {
				$('#dlg_footer').html('<button class="btn btn-secondary" id="dlg_btn_close" data-dismiss="modal">Close</button>');
			}
			
			$('#dlg').show().modal();
		},
		
		trackinit: function(course, skills, auditid, cmid) {
			function addSkillsEventHandlers() {

				function addSummaryClickHandlers() {
					$('.rating_td').click(function(e) {
						parts = e.currentTarget.id.split("_");
						var userid = parts[3];
						if(!userid) {
							return;
						}
						var skillid = parts[2];
						mod.showDialog("Summary", "Loading...", null, [{
							id:'send',
							text: 'Send message',
							close: true,
							onClick: function() {
								var comment = $('.rating_comment_editor').val();
								var id = $('.rating_comment_editor').attr('id');
								var parts = id.split("_");
								var cmid = parts[2];
								var skillid = parts[4];
								var userid = parts[3];
	                                                        var promises = ajax.call([{
	                                                                methodname: 'mod_skillsaudit_post_feedback',
	                                                                args: {cmid: cmid, skillid: skillid, userid: userid, comment: comment}
	                                                        }]);

	                                                        promises[0].done(function(response) {
	                                                                $('.latest_comment').html(response);
	                                                                //console.log(response);
	                                                        });
							}
						}, {
							id: 'close',
							text: 'Close',
							close: true
						}
						]);
						
						var promises = ajax.call([{
							methodname: 'mod_skillsaudit_get_activity_summary',
							args: {cmid: cmid, userid: userid, skillid: skillid}
						}]);
						
						promises[0].done(function(response) {
	                                            $('#dlg_body').html(response);
	                                            $('.btn_delete_feedback').click(function(e) {
	                                                var id = e.currentTarget.id.split('_')[3];
	                                                var pDelete = ajax.call([{
	                                                        methodname: 'mod_skillsaudit_delete_feedback',
	                                                        args: {cmid: cmid, feedbackid: id}
	                                                }]);

	                                                pDelete[0].done(function(response) {
	                                                        $('#teacher_feedback_' + response).remove();
	                                                });
	                                            });
							
						});
					});	
				}
				$('th.r_sortable').click(function(e) {

					var sortLink = $(e.currentTarget);
					if(sortLink.data('order') == "asc") {
						sortLink.data('order', "desc");
					} else {
						sortLink.data('order', "asc");
					}
					var order = sortLink.data('order');
					var table  = $(e.currentTarget).parents('table').eq(0);

					var tbody = table.find('tbody');
					var rows = [];
					tbody.find('tr').each(function(i, o) {
						rows.push(o.outerHTML);
					});
					tbody.html('');
					var sortby = $(e.currentTarget).data('col');
					rows.sort(function(a, b) {
						var valA = $(a).find("td[data-col='" + sortby + "']").data('sortable');
						var valB = $(b).find("td[data-col='" + sortby + "']").data('sortable');
						if(typeof(valA) == "string") {
							if(order == "asc") {
								return valA.localeCompare(valB);
							}
							return valB.localeCompare(valA);	
						}
						if(order == "asc") {
							return valA - valB;
						}
						return valB - valA;
					});

					for(var i = 0; i < rows.length; i++) {
						tbody.append(rows[i]);
					}
					addSummaryClickHandlers();
					

				});

				addSummaryClickHandlers();
				
			}
			
			function update() {
				var groupid = $('#select_group').val();
				var highlight = $('#highlight').val();
				var promises = ajax.call([{
					methodname: 'mod_skillsaudit_update_tracker',
					args: {cmid: cmid, groupid: groupid, highlight: highlight}
				}]);
				
				promises[0].done(function(response) {
					$('#tracker_table').html(response);
					addSkillsEventHandlers();
				});
			}
			$('#btn_update_progress_tracker').click(function() {
				update();
			});
			$('#select_group').change(update);
			$('#highlight').change(update);
			$('#autorefresh').change(function() {
				var interval = $(this).val() * 1000;
				clearInterval(mod.trackUpdateInterval);
				if(interval > 0) {
					mod.trackUpdateInterval = setInterval(update, interval);
				}
			});
			addSkillsEventHandlers();
		},
		
		viewinit: function(course, skills, auditid, cmid) {
			var i;
			var confidence = 0;
			var currentSkillId = -1;
		
			
			function onDeleteRating(e) {
				var id = e.currentTarget.id.replace("btn_delete_", "");
				mod.showDialog("Confirm delete", 'Are you sure you want to delete this rating?<p>If you press save, there\'s no way to undo this action</p>', null, [{
					id:'save', 
					text:'Save', 
					close:true, 
					onClick: function() {
						var promises = ajax.call([{
							methodname: 'mod_skillsaudit_delete_rating',
							args: {cmid: cmid, ratingid: id}
						}]);
						
						promises[0].done(function(response) {
							if(response.ratingID == id) {
								$('#rating_' + id).remove();
							}
							$('.skillsaudit_user_summary').html(response.summaryHtml);
							
							var confidence = $('#skill_row_' + currentSkillId + ' .minithumb').last().attr('data-confidence');
							if(!confidence)
								confidence = 0;
							var hue = Math.round(confidence * 120.0 / 100.0);
							$('#conf_ind_' + currentSkillId).css({width: confidence + '%', background: 'linear-gradient(to right,red,hsl(' + hue  + ',100%,50%)'});
							var ratingCount = $('#skill_row_' + currentSkillId).find('.rating').length;
							$('#rating_stats_' + currentSkillId + ' .rating_count').text(ratingCount);
							$('#skill_row_' + currentSkillId + ' .latest_rating_time').text('Last rated: today');
							
						});
					}
				}, 
				{id:'cancel', text:'Cancel', close:true}]);		
			}
			
			function onClearRating(e) {
				var id = e.currentTarget.id.replace("btn_clear_", "");
				mod.showDialog('Confirm clear', 'Are you sure you want to clear this comment?<p>If you press save, there\'s no way to undo this action</p>', null, [
					{
						id:'clear', text:'Clear', close:true, onClick: function() {
							var promises = ajax.call([{
								methodname: 'mod_skillsaudit_clear_rating',
								args: {cmid: cmid, ratingid: id}
							}]);
							
							promises[0].done(function(response) {
								if(response == id) {
									$('#rating_' + id + ' .rating_comment').remove();
								}
								
							});
						}
					},
					{
						id: 'cancel', text:'Cancel', close:true
					}
				]);
				
			}
			
			$('.btn_delete').click(onDeleteRating);
			$('.btn_clear').click(onClearRating);
			
			function drawChart(confidence) {
				var h = 120 * confidence / 100;
				var d = 180 - (180 * confidence / 100);
				$('#main_indicator').css({left:confidence + '%'}).find('.thumb').css({
					'background-color': 'hsl(' + h + ',100%,50%)',
					'transform': 'rotate(' + d + 'deg)'
				});
			}
						
			function showSkill(id) {
				currentSkillId = id;
				$('.skill_row').not('#skill_row_' + id).fadeOut();
				$('#skill_row_' + id + " .ratings").slideDown();
				confidence = 0;
				if(skills && skills[id] && skills[id].confidence)
					confidence = skills[id].confidence;

				var lo = $('#skill_description_' + id).text();
				var number = $('#skill_row_' + id + ' .skillnumber').text();
				$('.current_lo_title').text(number);
				$('.current_lo').text(lo);
					
				drawChart(confidence);
				$('#controls, #skill_row_' + id).fadeIn();
			}
			
			function saveSkill(whenDone) {
				var hue = Math.round(confidence * 120.0 / 100.0);
				$('#conf_ind_' + currentSkillId).css({width: confidence + '%', background: 'linear-gradient(to right,red,hsl(' + hue  + ',100%,50%)'}).parent().attr('title', confidence + '%');
				
				skills[currentSkillId].confidence = confidence;
				var comment = $('#id_comment').val();
				// fix for tinymce
				if(typeof(tinyMCE)!==undefined) {
					try {
						comment = tinyMCE.get('id_comment').getContent();	
					} catch(e) {

					}
					
				}
				var promises = ajax.call([{
					methodname: 'mod_skillsaudit_save_confidence',
					args: {courseid: course, skillid: currentSkillId, confidence: confidence, comment: comment, auditid:auditid}
				}]);
				
				promises[0].done(function(response) {
					$('#skill_row_' + currentSkillId + ' .ratings .new_ratings').append(response.ratingHtml);
					$('#skill_row_' + currentSkillId + ' .skillnumber').addClass('skill_included_yes');
					$('.btn_delete').unbind('click').click(onDeleteRating);
					$('.btn_clear').unbind('click').click(onClearRating);
					$('.skillsaudit_user_summary').html(response.summaryHtml);
					var ratingCount = $('#skill_row_' + currentSkillId).find('.rating').length;
					$('#rating_stats_' + currentSkillId + ' .rating_count').text(ratingCount);
					$('#skill_row_' + currentSkillId + ' .latest_rating_time').text('Last rated: today');
					if(whenDone)
						whenDone();
				});
			}
			
			$('#btn_save_confidence').click(function() {
				saveSkill(showAll);
			});
			
			$('.skill_row').click(function(e) {
				var id = e.currentTarget.id.replace('skill_row_', '');
				showSkill(id);
			});
			
			function showAll() {
				$('#controls').fadeOut();
				$('#skill_row_' + currentSkillId + " .ratings").slideUp();
				$('.skill_row').fadeIn();
				currentSkillId = -1
			}
			
			$('#btn_show_all').click(showAll);
			
			$('#btn_show_next').click(function(e) {
				saveSkill(function() {
					var rowId = $('#skill_row_' + currentSkillId).next().attr('id');
					showAll();
					if(rowId) {
						showSkill(rowId.replace('skill_row_', ''));
					}
				});
			});
			
			$('#controls').hide();
			
			$('.btn_hide_comments').click(function() {
				$('#skill_row_' + currentSkillId + " .ratings").slideUp();
				return false;
			});
			
			$('.btn_cancel').click(function() {
				showAll();
				return false;
			});
			
			var options = $('.btn_confidence');
				
			$('.btn_anim').click(function(e) {
				var id = e.currentTarget.id.replace('btn_confidence_', '')
				confidence = Math.round(id * 100.0 / (options.length - 1))
				drawChart(confidence);
				
			});
			
		},
        forminit: function(course, modpath) {
			$('#id_selectall').click(function() {
				$('.skill_included').removeClass('skill_included_no').addClass('skill_included_yes');
				updateListOfSelectedSkills();
			});
			
			$('#id_selectnone').click(function() {
				$('.skill_included').removeClass('skill_included_yes').addClass('skill_included_no');
				updateListOfSelectedSkills();
			});
			
			$('#id_deleteunused').click(function() {
				mod.showDialog('Confirm delete', 'Are you sure you want to delete all skills that aren\'t used in this course?<p>If you press save, there\'s no way to undo this action</p>', null, [
					{
						id:'delete', text: 'Delete', close:true, onClick: function() {
							var ids = [];
							$('.skill_included_no').each(function(i, e) {
								ids.push(e.id.replace('skill_included_',''));
							});
							var promises = ajax.call([{
								methodname: 'mod_skillsaudit_delete_unused_skills',
								args: {courseid: course, skillids: ids.join(',')}
							}]);
							
							promises[0].done(function(response) {
								var ids = response.split(',');
								for(var i = 0; i < ids.length; i++) {
									$('#skill_row_' + ids[i]).remove();
								}
								
							});
						}
					},
					{
						id: 'cancel', text: 'Cancel', close:true
					}
				]);
			});
			
			
			function updateListOfSelectedSkills() {
				var ids = "";
				$('.skill_included_yes').each(function(i, skill) {
					ids = ids + skill.id.replace('skill_included_', '') + ",";
				});
				ids = ids.replace(/,$/, '');
				$("input[name='skills']").val(ids);
			}
			
			function onSkillClick(e) {
				var t = $(e.target);
				if(t.hasClass('skill_included_yes')) {
					t.removeClass('skill_included_yes');
					t.addClass('skill_included_no');
				} else {
					t.removeClass('skill_included_no');
					t.addClass('skill_included_yes');
					url = t.attr('src');
				}
				
				// update list of selected skills
				updateListOfSelectedSkills();
			}
			
			function onSkillDblClick(e) {
				var t = $(e.target);
				
				// loop through parents until we find the table row
				var c = 0;
				while(!t.attr('id').match(/skill_row_/)){
					if(c++ > 4)break;
					t = t.parent();
				}
				
				var id = t.attr('id').replace('skill_row_', '');
				var number = t.find('.skill_number').text();
				var desc = t.find('.skill_description').text();
				var link = t.find('.skill_help_link').attr('href');
				mod.showDialog("Edit skill", '<h3>Note:</h3>Editing a skill here will affect all skills audits that include that skill in this course<div class="form-group"><label for="skill_edit_number"><h4>Spec. number:</h4></label><input class="form-control" id="skill_edit_number" value="' + number + '"></div><div class="form-group"><label for="skill_edit_description"><h4>Description:</h4></label><input class="form-control" id="skill_edit_description" value="' + desc + '"><label for="skill_edit_link"><h4>Help link:</h4></label><input class="form-control" id="skill_edit_link" value="' + link + '"><h3>Warning</h3>Deleting this skill cannot be undone. It will remove the skill from this audit and any others in this course.', null, [
					{
						id: 'save', text: 'Save', close:true, onClick: function() {
							desc = $('#skill_edit_description').val();
							number = $('#skill_edit_number').val();
							helpLink = $('#skill_edit_link').val();
							var promises = ajax.call([{
								methodname: 'mod_skillsaudit_edit_skill',
								args: {courseid: course, skillid: id, number: number, description: desc, link:helpLink}
							}]);
							promises[0].done(function(response) {
								t.find('.skill_description').text(desc);
								if(helpLink.length > 0) {
									number = '<span class="info_icon"></span>' + number;
								}
								t.find('.skill_help_link').attr('href', helpLink).html(number);
							});
						}
					}, 
					
					{
						id: 'delete', text: 'Delete', close:true, onClick: function() {
							var promises = ajax.call([{
								methodname: 'mod_skillsaudit_delete_skill',
								args: {courseid: course, skillid: id}
							}]);
							
							promises[0].done(function(response) {
								// remove skill from the table
								$('#skill_row_' + id).remove();
							});
						}
					},
					
					{
						id: 'cancel', text: 'Cancel', close:true
					}
				]);				
			}
			
			// included / not included toggle
			$('.skill_included').click(onSkillClick);
			$('.skill_row').dblclick(onSkillDblClick);
			
			$('#id_addnew').click(function() {
				var verifiedSkills = [];
				mod.showDialog("Check new skills", '<div id="skills_preview">', function() {
					var skills = $('#id_newskills').val().split("\n");
					var html = '<table class="generaltable"><tr><th>Number</th><th>Description</th><th>Help link</th></tr>';
					
					$.each(skills, function(i, value) {
						var parts = value.trim().match(/^(.*?)\s+(.*?)\s*(https?:\/\/.*)?$/m);
						var number = "";
						var helpLink = "";
						var description = value;
						if(parts && parts.length > 2) {
							number = parts[1].trim();
							description = parts[2].trim();
							if(parts.length > 3 && parts[3]) {
								helpLink = parts[3].trim();
							}
						}
						var helpHtml = '';
						if(helpLink.length > 0) {
							helpHtml += '<a href="' + helpLink + '" target="_blank"><span class="info_icon"></span></a>';
						}
						verifiedSkills.push({number: number, description: description, link: helpLink});
						html += '<tr><td>' + number + '</td><td>' + description + '</td><td>' + helpHtml + '</td></tr>';
					});
					html += '</table>';
					$('#skills_preview').html(html);
				}, [
					{
						id: 'add', text: 'Add', close: true, onClick: function() {
							var promises = ajax.call([{
								methodname: 'mod_skillsaudit_add_skills',
								args: {courseid: course, skills: verifiedSkills}
							}]);
							
							promises[0].done(function(response) {
								// add new skills to list option
								var tbl = $('#tbl_skills');
								$.each(response, function(i, skill) {
									tbl.append('<tr class="skill_row" id="skill_row_' + skill.id + '"><td class="skill_number"><a class="skill_help_link" href="' + skill.link + '">' + (skill.link.length > 0?'<span class="info_icon"></span>':'') + skill.number + '</a></td><td class="skill_description">' + skill.description + '</td><td><span id="skill_included_' + skill.id + '" class="skill_included_yes skill_included"></span></td></tr>');
									$('#skill_included_' + skill.id).click(onSkillClick);
									$('#skill_row_' + skill.id).dblclick(onSkillDblClick);
								});
								updateListOfSelectedSkills();
								
								// clear new skills add box
								$('#id_newskills').val('');
								
							}).fail(function(response) {
								$('#skills_preview').html("Could not add skill");
							});
						}
					},
					{
						id: 'cancel', text: 'Cancel', close: true
					}
				]);
			});
        }
    };
	return mod;
});
