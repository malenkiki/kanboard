<?php

namespace Controller;

class Board extends Base
{
    // Change a task assignee directly from the board
    public function assign()
    {
        $task = $this->task->getById($this->request->getIntegerParam('task_id'));
        $project = $this->project->get($task['project_id']);
        $projects = $this->project->getListByStatus(\Model\Project::ACTIVE);

        $this->response->html($this->template->layout('board_assign', array(
            'errors' => array(),
            'values' => $task,
            'users_list' => $this->user->getList(),
            'projects' => $projects,
            'current_project_id' => $project['id'],
            'current_project_name' => $project['name'],
            'menu' => 'boards',
            'title' => t('Change assignee').' - '.$task['title'],
        )));
    }

    // Validate an assignee change
    public function assignTask()
    {
        $values = $this->request->getValues();
        list($valid,) = $this->task->validateAssigneeModification($values);

        if ($valid && $this->task->update($values)) {
            $this->session->flash(t('Task updated successfully.'));
        }
        else {
            $this->session->flashError(t('Unable to update your task.'));
        }

        $this->response->redirect('?controller=board&action=show&project_id='.$values['project_id']);
    }

    // Display the public version of a board
    // Access checked by a simple token, no user login, read only, auto-refresh
    public function readonly()
    {
        $token = $this->request->getStringParam('token');
        $project = $this->project->getByToken($token);

        // Token verification
        if (! $project) {
            $this->response->text('Not Authorized', 401);
        }

        // Display the board with a specific layout
        $this->response->html($this->template->layout('board_public', array(
            'project' => $project,
            'columns' => $this->board->get($project['id']),
            'title' => $project['name'],
            'no_layout' => true,
            'auto_refresh' => true,
        )));
    }

    // Display the default user project or the first project
    public function index()
    {
        $projects = $this->project->getListByStatus(\Model\Project::ACTIVE);

        if (! count($projects)) {
            $this->redirectNoProject();
        }
        else if (! empty($_SESSION['user']['default_project_id']) && isset($projects[$_SESSION['user']['default_project_id']])) {
            $project_id = $_SESSION['user']['default_project_id'];
            $project_name = $projects[$_SESSION['user']['default_project_id']];
        }
        else {
            list($project_id, $project_name) = each($projects);
        }

        $this->response->html($this->template->layout('board_index', array(
            'projects' => $projects,
            'current_project_id' => $project_id,
            'current_project_name' => $project_name,
            'columns' => $this->board->get($project_id),
            'menu' => 'boards',
            'title' => $project_name
        )));
    }

    // Show a board for a given project
    public function show()
    {
        $projects = $this->project->getListByStatus(\Model\Project::ACTIVE);
        $project_id = $this->request->getIntegerParam('project_id');
        $project_name = $projects[$project_id];

        $this->response->html($this->template->layout('board_index', array(
            'projects' => $projects,
            'current_project_id' => $project_id,
            'current_project_name' => $project_name,
            'columns' => $this->board->get($project_id),
            'menu' => 'boards',
            'title' => $project_name
        )));
    }

    // Display a form to edit a board
    public function edit()
    {
        $this->checkPermissions();

        $project_id = $this->request->getIntegerParam('project_id');
        $project = $this->project->get($project_id);
        $columns = $this->board->getColumnsList($project_id);
        $values = array();

        foreach ($columns as $column_id => $column_title) {
            $values['title['.$column_id.']'] = $column_title;
        }

        $this->response->html($this->template->layout('board_edit', array(
            'errors' => array(),
            'values' => $values + array('project_id' => $project_id),
            'columns' => $columns,
            'project' => $project,
            'menu' => 'projects',
            'title' => t('Edit board')
        )));
    }

    // Validate and update a board
    public function update()
    {
        $this->checkPermissions();

        $project_id = $this->request->getIntegerParam('project_id');
        $project = $this->project->get($project_id);
        $columns = $this->board->getColumnsList($project_id);
        $data = $this->request->getValues();
        $values = array();

        foreach ($columns as $column_id => $column_title) {
            $values['title['.$column_id.']'] = isset($data['title'][$column_id]) ? $data['title'][$column_id] : '';
        }

        list($valid, $errors) = $this->board->validateModification($columns, $values);

        if ($valid) {

            if ($this->board->update($data['title'])) {
                $this->session->flash(t('Board updated successfully.'));
                $this->response->redirect('?controller=board&action=edit&project_id='.$project['id']);
            }
            else {
                $this->session->flashError(t('Unable to update this board.'));
            }
        }

        $this->response->html($this->template->layout('board_edit', array(
            'errors' => $errors,
            'values' => $values + array('project_id' => $project_id),
            'columns' => $columns,
            'project' => $project,
            'menu' => 'projects',
            'title' => t('Edit board')
        )));
    }

    // Validate and add a new column
    public function add()
    {
        $this->checkPermissions();

        $project_id = $this->request->getIntegerParam('project_id');
        $project = $this->project->get($project_id);
        $columns = $this->board->getColumnsList($project_id);
        $data = $this->request->getValues();
        $values = array();

        foreach ($columns as $column_id => $column_title) {
            $values['title['.$column_id.']'] = $column_title;
        }

        list($valid, $errors) = $this->board->validateCreation($data);

        if ($valid) {

            if ($this->board->add($data)) {
                $this->session->flash(t('Board updated successfully.'));
                $this->response->redirect('?controller=board&action=edit&project_id='.$project['id']);
            }
            else {
                $this->session->flashError(t('Unable to update this board.'));
            }
        }

        $this->response->html($this->template->layout('board_edit', array(
            'errors' => $errors,
            'values' => $values + $data,
            'columns' => $columns,
            'project' => $project,
            'menu' => 'projects',
            'title' => t('Edit board')
        )));
    }

    // Confirmation dialog before removing a column
    public function confirm()
    {
        $this->checkPermissions();

        $this->response->html($this->template->layout('board_remove', array(
            'column' => $this->board->getColumn($this->request->getIntegerParam('column_id')),
            'menu' => 'projects',
            'title' => t('Remove a column from a board')
        )));
    }

    // Remove a column
    public function remove()
    {
        $this->checkPermissions();

        $column = $this->board->getColumn($this->request->getIntegerParam('column_id'));

        if ($column && $this->board->removeColumn($column['id'])) {
            $this->session->flash(t('Column removed successfully.'));
        } else {
            $this->session->flashError(t('Unable to remove this column.'));
        }

        $this->response->redirect('?controller=board&action=edit&project_id='.$column['project_id']);
    }

    // Save the board (Ajax request made by the drag and drop)
    public function save()
    {
        $this->response->json(array(
            'result' => $this->board->saveTasksPosition($this->request->getValues())
        ));
    }
}
