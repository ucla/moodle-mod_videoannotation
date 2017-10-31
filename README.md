# Intro

The video annotation activity allows students to place tags directly to an online streaming video clip from YouTube or the Communication Studies Digital Archive (https://tvnews.sscnet.ucla.edu/public/).

Instructors have two options when setting up this activity: students can pick a clip themselves or instructors as can assign a clip for the students to watch. Each student will then go in and provide his or her annotations to submit for a grade. For a quick overview on how to use the Video Annotation tool as a student, please view this 3:30mins YouTube video: https://www.youtube.com/watch?v=i26QSgk3IkI&t=8s.

# Installation

Inside your `moodle/mod` folder you run the following command:

```
git clone --master https://github.com/ucla/moodle-mod_videoannotation.git && git submodule update --init
```

Then sign into Moodle as a site admin and run "Update database." Settings for Video Annotation can be found at: Site administration > Plugins > Plugins overview > Video Annotation. Enter a streaming server if you would like the Video Annotation Tool to access this server.

# Using the Video Annotation Tool

## Adding a Video Annotation activity to the site
1. Go to a Moodle course site
2. Turn Editing on
3. Click Add an activity or resource
4. Select the **Video Annotation** from the list
5. Click **Submit**
6. You will be sent to a new page to enter details about your new activity.
7. Provide a **Name** and a **Description**
8. Decide whether students will pick their own clips to annotate or they will annotate a predetermined clip.
   1. If using a predetermined clip, enter URL of clip, start and end time, and video player size.
   2. Press **Preview** and verify that the clip works (if the clip cannot be previewed, you cannot save the settings)
9. Determine if you wish to enable group mode
   * Note: group mode allows users within the same group to edit the same Video Annotation entry
10. Click ***Save and display***

## Add a Clip
If "Students pick their own clips" is selected for "Clip Source", students will be prompted to add a clip before beginning. Clicking Add clip will load the page where the student can input the clip URL and details.

Here are the list of URL formats that are supported in the Video Annotation Tool:
* Youtube - https://youtube.com/ (Simply copy the URL that appears for a video, e.g. https://www.youtube.com/watch?v=9zhegwiAEug)
* The UCLA Library Broadcast NewsScape - http://newsscape.library.ucla.edu/

## Annotating Video
1. Play the video
2. Pause the video, then click ***Add Tag***
   * Provide a name and reference color
   * Click ***Save***
3. Play the video
4. Click on ***Start*** for the tag you just added
5. Click ***Stop*** to end the point for an event
6. Double click on the event in the timeline to add contextual content for the event
7. Click ***Submit*** when ready for feedback

## Copying a Video Annotation
Video annotations can be copied, either to the same course as a fork, or to a different course.

1. Select ***View annotation report*** in the upper right corner; you should see the full report.
2. Click on ***Download report*** and save the csv file on your computer.
3. Go to the course where you want to import the annotation. Click on ***Add an activity*** at the bottom of the page and select ***Video annotation***.
4. Copy the fields from the source annotation to the target, click ***Preview***, and then ***Save and display***.
    In the upper right-hand corner, select ***Import annotation***. Browse to the saved csv file and click ***Submit***.

You should see ***The import succeeded!***

# Changelog

v1.0
* Initial release
