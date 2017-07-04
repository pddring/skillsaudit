define(['jquery', 'core/modal_factory', 'core/modal_events', 'core/ajax'], function($, ModalFactory, ModalEvents, ajax) {
    var mod = {
		viewinit: function(course, skills, auditid, cmid) {
			var i;
			var confidence = 0;
			var currentSkillId = -1;
			
			$('.btn_edit').click(function(e) {
				//$('#id_comment').html('testing testing 123');
				//console.log("Hello");
				///TODO: edit comments
			});
			
			$('.btn_delete').click(function(e) {
				var id = e.currentTarget.id.replace("btn_delete_", "");
				
				ModalFactory.create({
					type: ModalFactory.types.SAVE_CANCEL,
					title: 'Confirm delete',
					body: 'Are you sure you want to delete this rating?<p>If you press save, there\'s no way to undo this action</p>'
				}).done(function(modal){
					modal.show();
					var r = modal.getRoot();
					r.on(ModalEvents.save, function(e) {
						var promises = ajax.call([{
							methodname: 'mod_skillsaudit_delete_rating',
							args: {cmid: cmid, ratingid: id}
						}]);
						
						promises[0].done(function(response) {
							if(response == id) {
								$('#rating_' + id + ' .rating_comment').remove();
							}
							
						});
					});
				});				
				
			});
			
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
					
				drawChart(confidence);
				$('#controls, #skill_row_' + id).fadeIn();
			}
			
			function saveSkill(whenDone) {
				var hue = Math.round(confidence * 120.0 / 100.0);
				$('#conf_ind_' + currentSkillId).css({width: confidence + '%', background: 'linear-gradient(to right,red,hsl(' + hue  + ',100%,50%)'});
				skills[currentSkillId].confidence = confidence;
				var comment = $('#id_comment').val();
				var promises = ajax.call([{
					methodname: 'mod_skillsaudit_save_confidence',
					args: {courseid: course, skillid: currentSkillId, confidence: confidence, comment: comment, auditid:auditid}
				}]);
				
				promises[0].done(function(response) {
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
					if(rowId === undefined) {
						showAll();
					} else {
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
			
			ModalFactory.create({
				type: ModalFactory.types.SAVE_CANCEL,
				title: 'Confirm delete',
				body: 'Are you sure you want to delete all skills that aren\'t used in this course?<p>If you press save, there\'s no way to undo this action</p>'
			}, $('#id_deleteunused')).done(function(modal){
				var r = modal.getRoot();
				r.on(ModalEvents.save, function(e) {
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
				});
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
				ModalFactory.create({
					type: ModalFactory.types.SAVE_CANCEL,
					title: "Edit skill",
					body: '<h3>Note:</h3>Editing a skill here will affect all skills audits that include that skill in this course<h4>Spec. number:</h4><input id="skill_edit_number" value="' + number + '"><h4>Description</h4><input id="skill_edit_description" value="' + desc + '"><h3>Warning</h3>Deleting this skill cannot be undone. It will remove the skill from this audit and any others in this course.<p><button id="btn_delete_skill">Delete</button></p>'
				}).done(function(modal) {
					modal.show();
					$('#btn_delete_skill').click(function(e) {
						var promises = ajax.call([{
							methodname: 'mod_skillsaudit_delete_skill',
							args: {courseid: course, skillid: id}
						}]);
						
						promises[0].done(function(response) {
							// remove skill from the table
							$('#skill_row_' + id).remove();
						});
						modal.hide();
						modal.destroy();
					});
					
					var r = modal.getRoot();
					r.on(ModalEvents.save, function(e) {
						desc = $('#skill_edit_description').val();
						number = $('#skill_edit_number').val();
						var promises = ajax.call([{
							methodname: 'mod_skillsaudit_edit_skill',
							args: {courseid: course, skillid: id, number: number, description: desc}
						}]);
						promises[0].done(function(response) {
							t.find('.skill_number').text(number);
							t.find('.skill_description').text(desc);
						});
					});
				});
				
			}
			
			// included / not included toggle
			$('.skill_included').click(onSkillClick);
			$('.skill_row').dblclick(onSkillDblClick);
			
			
			ModalFactory.create({
				type: ModalFactory.types.SAVE_CANCEL,
				title: "Check new skills",
				body: '<div id="skills_preview">'
			}, $('#id_addnew')).done(function(modal){
				var r = modal.getRoot();
				var verifiedSkills = [];
				r.on(ModalEvents.save, function(e) {
					var promises = ajax.call([{
						methodname: 'mod_skillsaudit_add_skills',
						args: {courseid: course, skills: verifiedSkills}
					}]);
					
					promises[0].done(function(response) {
						// add new skills to list option
						var tbl = $('#tbl_skills');
						$.each(response, function(i, skill) {
							tbl.append('<tr class="skill_row" id="skill_row_' + skill.id + '"><td class="skill_number">' + skill.number + '</td><td class="skill_description">' + skill.description + '</td><td><span id="skill_included_' + skill.id + '" class="skill_included_yes skill_included"></span></td></tr>');
							$('#skill_included_' + skill.id).click(onSkillClick);
						});
						updateListOfSelectedSkills();
						
						// clear new skills add box
						$('#id_newskills').val('');
						
					}).fail(function(response) {
						$('#skills_preview').html("Could not add skill");
					});
				});
				
				r.on(ModalEvents.shown, function(e) {
					var skills = $('#id_newskills').val().split("\n");
					var html = '<table class="generaltable"><tr><th>Number</th><th>Description</th></tr>';
					
					$.each(skills, function(i, value) {
						var parts = value.match(/^([^ ]*) (.*)$/);
						var number = "";
						var description = value;
						if(parts && parts.length > 2) {
							number = parts[1];
							description = parts[2];
						}
						verifiedSkills.push({number: number, description: description});
						html += '<tr><td>' + number + '</td><td>' + description + '</td></tr>';
					});
					html += '</table><p>If the table above is correct, press Save Changes to add the skill(s). Otherwise, press cancel</p>';
					$('#skills_preview').html(html);
				});
				
			});
        }
    };
	return mod;
});